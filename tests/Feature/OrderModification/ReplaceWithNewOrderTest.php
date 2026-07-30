<?php

use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function paidOrderWithoutRealInvoice(float $total = 100.0): Order
{
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'invoice_id' => 'PROFORMA',
        'total' => $total,
        'subtotal' => $total,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Oud product',
        'quantity' => 1,
        'price' => $total,
        'vat_rate' => 21,
    ]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => $total, 'psp' => 'own']);

    return $order->fresh();
}

function line(string $name, float $price, int $quantity = 1): array
{
    return [
        'order_product_id' => null,
        'product_id' => null,
        'name' => $name,
        'quantity' => $quantity,
        'price' => $price,
        'vat_rate' => 21,
        'product_extras' => [],
    ];
}

/**
 * Exact het patroon uit tests/Feature/OrderModification/CancelRestockOptionTest.php:
 * name en slug zijn translatable arrays, de save-hook wordt uitgezet en de
 * queue gefaket omdat UpdateProductInformationJob MySQL-only SQL gebruikt.
 */
function replacementStockedProduct(int $stock): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'group-'.uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Voorraadproduct'],
        'slug' => ['en' => 'voorraad-'.uniqid()],
        'site_ids' => ['site'],
        'price' => 10, 'current_price' => 10, 'vat_rate' => 21,
        'use_stock' => true, 'stock' => $stock,
        'images' => [],
    ]));
}

/**
 * Een betaalde order zonder echte factuur, met een regel die aan een
 * voorraad-product hangt zodat markAsCancelled()/deductStock() er iets aan
 * kunnen veranderen.
 */
function paidOrderWithStockedLine(Product $product, int $quantity, float $total = 100.0): Order
{
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'invoice_id' => 'PROFORMA',
        'total' => $total,
        'subtotal' => $total,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => 'Oud voorraadproduct',
        'quantity' => $quantity,
        'price' => $total,
        'vat_rate' => 21,
    ]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => $total, 'psp' => 'own']);

    return $order->fresh();
}

it('maakt een vervangende order met het verschil als openstaand bedrag', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [line('Duurder product', 121.0)]);

    expect(round($new->total, 2))->toBe(121.0)
        ->and(round($new->outstandingAmount(), 2))->toBe(21.0)
        ->and($new->status)->toBe('partially_paid')
        ->and($new->orderProducts()->count())->toBe(1);
});

it('verplaatst de betalingen naar de vervangende order', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [line('Duurder product', 121.0)]);

    expect($new->orderPayments()->where('status', 'paid')->count())->toBe(1)
        ->and($old->fresh()->orderPayments()->where('status', 'paid')->count())->toBe(0);
});

it('annuleert de oude order en koppelt hem aan de nieuwe', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [line('Ander product', 100.0)]);

    expect($old->fresh()->status)->toBe('cancelled')
        ->and($old->fresh()->replaced_by_order_id)->toBe($new->id)
        ->and($new->fresh()->replacesOrder->id)->toBe($old->id)
        ->and($new->invoice_id)->not->toBe($old->invoice_id);
});

it('zet de vervangende order op betaald bij een gelijk totaal', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [line('Ander product, zelfde prijs', 100.0)]);

    expect($new->status)->toBe('paid')
        ->and(round($new->outstandingAmount(), 2))->toBe(0.0)
        ->and(round($new->total, 2))->toBe(100.0);
});

it('laat een teveel betaald bedrag zien bij een lager totaal', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [line('Goedkoper product', 80.0)]);

    expect(round($new->overpaidAmount(), 2))->toBe(20.0)
        ->and($new->status)->toBe('paid');
});

it('zet geen herstelmails in de wachtrij voor de geannuleerde order', function () {
    $flow = AbandonedCartFlow::create([
        'name' => 'Herstel', 'is_active' => true,
        'discount_prefix' => 'P', 'triggers' => ['cancelled_order'],
    ]);
    AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'Herstel je bestelling :orderId:',
        'enabled' => true,
        'blocks' => [['type' => 'text', 'data' => ['content' => '<p>Hoi</p>']]],
    ]);

    $old = paidOrderWithoutRealInvoice(100.0);

    OrderModificationService::replaceWithNewOrder($old, [line('Ander product', 100.0)]);

    expect(AbandonedCartEmail::count())->toBe(0);
});

it('weigert een order te vervangen die al vervangen is', function () {
    $old = paidOrderWithoutRealInvoice(100.0);
    $old->replaced_by_order_id = Order::create(['email' => 'a@b.nl', 'status' => 'pending'])->id;
    $old->save();

    expect(fn () => OrderModificationService::replaceWithNewOrder($old, [line('Product', 100.0)]))
        ->toThrow(LogicException::class);
});

it('boekt de voorraad van de nieuwe order af en geeft de oude terug', function () {
    $product = replacementStockedProduct(stock: 5);
    $old = paidOrderWithStockedLine($product, quantity: 2, total: 100.0);

    $newLine = line('Nieuw voorraadproduct', 100.0, quantity: 3);
    $newLine['product_id'] = $product->id;

    // Standaardpad: already_shipped blijft false (oude order boekt 2 terug),
    // deduct_new_stock blijft true (nieuwe order boekt 3 af). Netto: 5 + 2 - 3 = 4.
    OrderModificationService::replaceWithNewOrder($old, [$newLine]);

    expect($product->fresh()->stock)->toBe(4);
});

it('laat de oude voorraad staan en boekt de nieuwe niet af bij een administratieve correctie', function () {
    $product = replacementStockedProduct(stock: 5);
    $old = paidOrderWithStockedLine($product, quantity: 2, total: 100.0);

    $newLine = line('Nieuw voorraadproduct', 100.0, quantity: 3);
    $newLine['product_id'] = $product->id;

    // Niets is opnieuw verzonden: already_shipped => true laat de oude
    // voorraad staan (geen terugboeking), deduct_new_stock => false laat de
    // nieuwe order de voorraad niet nogmaals afboeken. Netto blijft 5.
    OrderModificationService::replaceWithNewOrder($old, [$newLine], [
        'already_shipped' => true,
        'deduct_new_stock' => false,
    ]);

    expect($product->fresh()->stock)->toBe(5);
});
