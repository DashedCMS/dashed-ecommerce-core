<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * writeLines() neemt sku, discount en is_pre_order van de bronregel over, want
 * het wijzigformulier toont die velden niet. Het formulier houdt
 * order_product_id echter vast wanneer de beheerder in dezelfde regel een ander
 * product kiest, dus die overname mag niet alleen op order_product_id afgaan:
 * dan houdt een omgezette regel de sku (en de korting) van zijn voorganger, en
 * stond daar 'shipping_costs', dan boekt een echt product zich als verzendomzet.
 */
beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
});

function skuCarryProduct(string $sku, float $price = 50.0): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'sku-group-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Product ' . $sku],
        'slug' => ['en' => 'sku-' . uniqid()],
        'site_ids' => ['site'],
        'sku' => $sku,
        'price' => $price, 'current_price' => $price, 'vat_rate' => 21,
        'use_stock' => false, 'stock' => 0,
        'images' => [],
    ]));
}

function skuCarryOrder(): Order
{
    return Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'total' => 100, 'subtotal' => 100]);
}

it('geeft een omgezette regel de sku van het nieuwe product en niet die van de oude regel', function () {
    $oldProduct = skuCarryProduct('SKU-OUD');
    $newProduct = skuCarryProduct('SKU-NIEUW');

    $order = skuCarryOrder();
    $source = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $oldProduct->id,
        'name' => 'Oud product',
        'quantity' => 1,
        'price' => 50.0,
        'vat_rate' => 21,
        'sku' => 'SKU-OUD',
        'discount' => 7.5,
    ]);

    // Precies wat het formulier doorgeeft nadat de beheerder in deze regel een
    // ander product kiest: product_id en name veranderen mee, order_product_id
    // blijft naar de oorspronkelijke regel wijzen.
    OrderModificationService::applyInPlace($order->fresh(), [
        ['order_product_id' => $source->id, 'product_id' => $newProduct->id, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => 50.0, 'vat_rate' => 21, 'product_extras' => []],
    ], ['send_customer_email' => false]);

    $line = $order->fresh()->orderProducts()->first();

    expect($line->sku)->toBe('SKU-NIEUW')
        ->and(round((float) $line->discount, 2))->toBe(0.0);
});

it('maakt van een omgezette verzendkostenregel geen verzendomzet', function () {
    $product = skuCarryProduct('SKU-ECHT');

    $order = skuCarryOrder();
    $shipping = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => null,
        'name' => 'Verzendkosten',
        'quantity' => 1,
        'price' => 7.5,
        'vat_rate' => 21,
        'sku' => 'shipping_costs',
    ]);

    OrderModificationService::applyInPlace($order->fresh(), [
        ['order_product_id' => $shipping->id, 'product_id' => $product->id, 'name' => 'Echt product', 'quantity' => 1, 'price' => 50.0, 'vat_rate' => 21, 'product_extras' => []],
    ], ['send_customer_email' => false]);

    expect($order->fresh()->orderProducts()->first()->sku)->toBe('SKU-ECHT');
});

it('geeft een nieuw toegevoegde productregel de sku van dat product in plaats van niets', function () {
    $product = skuCarryProduct('SKU-TOEGEVOEGD');

    $order = skuCarryOrder();
    $existing = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Bestaande regel',
        'quantity' => 1,
        'price' => 100.0,
        'vat_rate' => 21,
        'sku' => 'SKU-BESTAAND',
    ]);

    OrderModificationService::applyInPlace($order->fresh(), [
        ['order_product_id' => $existing->id, 'product_id' => null, 'name' => 'Bestaande regel', 'quantity' => 1, 'price' => 100.0, 'vat_rate' => 21, 'product_extras' => []],
        // Een regel die de beheerder via "Regel toevoegen" aanmaakt heeft geen
        // order_product_id; zonder afleiding uit het product blijft de sku leeg
        // en valt de hele order uit de verkochte-aantallen (`sku NOT IN (...)`
        // is bij NULL nooit waar).
        ['order_product_id' => null, 'product_id' => $product->id, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => 50.0, 'vat_rate' => 21, 'product_extras' => []],
    ], ['send_customer_email' => false]);

    $lines = $order->fresh()->orderProducts()->get()->keyBy('name');

    expect($lines['Nieuw product']->sku)->toBe('SKU-TOEGEVOEGD')
        ->and($lines['Bestaande regel']->sku)->toBe('SKU-BESTAAND');
});

it('houdt de eigen sku van een ongewijzigde productregel aan, ook als het product inmiddels een andere sku heeft', function () {
    // De sku op de orderregel is een momentopname van het moment van bestellen.
    // Zolang de regel over hetzelfde product gaat, blijft die momentopname staan
    // en wordt hij niet stilletjes vervangen door de huidige productsku.
    $product = skuCarryProduct('SKU-HUIDIG');

    $order = skuCarryOrder();
    $source = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => 'Product',
        'quantity' => 1,
        'price' => 50.0,
        'vat_rate' => 21,
        'sku' => 'SKU-HISTORISCH',
        'discount' => 5.0,
    ]);

    OrderModificationService::applyInPlace($order->fresh(), [
        ['order_product_id' => $source->id, 'product_id' => $product->id, 'name' => 'Product', 'quantity' => 2, 'price' => 100.0, 'vat_rate' => 21, 'product_extras' => []],
    ], ['send_customer_email' => false]);

    $line = $order->fresh()->orderProducts()->first();

    expect($line->sku)->toBe('SKU-HISTORISCH')
        ->and(round((float) $line->discount, 2))->toBe(5.0);
});
