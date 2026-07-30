<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Classes\OrderTotalsCalculator;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
});

function percentageCode(float $percentage = 10.0, string $validFor = 'all'): DiscountCode
{
    return DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Procentuele korting',
        'code' => 'PCT-' . strtoupper(uniqid()),
        'type' => 'percentage',
        'discount_percentage' => $percentage,
        'valid_for' => $validFor,
        'use_stock' => 0,
    ]);
}

function amountCode(float $amount = 15.0): DiscountCode
{
    return DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => 'Vaste korting',
        'code' => 'VAST-' . strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => $amount,
        'use_stock' => 0,
    ]);
}

function percentageProduct(float $price = 100.0): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'pct-group-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Kortingsproduct'],
        'slug' => ['en' => 'pct-' . uniqid()],
        'site_ids' => ['site'],
        'price' => $price, 'current_price' => $price, 'vat_rate' => 21,
        'use_stock' => false, 'stock' => 0,
        'images' => [],
    ]));
}

/**
 * @param  array<int, array<string, mixed>>  $lines
 */
function orderWithCode(?DiscountCode $code, array $lines, float $discount = 0.0): Order
{
    $order = new Order();
    $order->email = 'a@b.nl';
    $order->status = 'pending';
    $order->discount_code_id = $code?->id;
    $order->discount = $discount;
    $order->save();

    foreach ($lines as $line) {
        OrderProduct::create(array_merge([
            'order_id' => $order->id,
            'name' => 'Regel',
            'quantity' => 1,
            'vat_rate' => 21,
        ], $line));
    }

    return $order->fresh();
}

it('herberekent een procentuele korting over het nieuwe subtotaal', function () {
    // Order stond op 100 met 10% (= 10 korting). Er komt 100 bij, dus de
    // korting hoort 20 te worden. Bleef hij op 10 staan, dan betaalde de klant
    // 10 te veel.
    $code = percentageCode(10.0);
    $order = orderWithCode($code, [
        ['price' => 100.0],
        ['price' => 100.0],
    ], discount: 10.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(200.0)
        ->and(round($order->discount, 2))->toBe(20.0)
        ->and(round($order->total, 2))->toBe(180.0);
});

it('bevriest een kortingscode met een vast bedrag', function () {
    // Bij een vast bedrag is bevriezen juist correct: dat bedrag is bij het
    // afrekenen afgesproken en beweegt niet mee met de inhoud.
    $code = amountCode(15.0);
    $order = orderWithCode($code, [
        ['price' => 100.0],
        ['price' => 100.0],
    ], discount: 15.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(200.0)
        ->and(round($order->discount, 2))->toBe(15.0);
});

it('laat een order zonder kortingscode ongemoeid', function () {
    $order = orderWithCode(null, [['price' => 100.0]], discount: 25.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->discount, 2))->toBe(25.0);
});

it('rekent een procentuele korting niet over verzend- en betaalkosten', function () {
    // Net als in de winkelwagen: een procentuele code geldt over de producten,
    // niet over de kosten-regels. 10% over 100 = 10, niet 10% over 112,50.
    $code = percentageCode(10.0);
    $order = orderWithCode($code, [
        ['price' => 100.0],
        ['price' => 7.5, 'sku' => 'shipping_costs', 'name' => 'Verzendkosten'],
        ['price' => 5.0, 'sku' => 'payment_costs', 'name' => 'Betaalkosten'],
    ]);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(112.5)
        ->and(round($order->discount, 2))->toBe(10.0)
        ->and(round($order->total, 2))->toBe(102.5);
});

it('past een procentuele korting alleen toe op de producten waarvoor de code geldt', function () {
    // valid_for = 'products' met één gekoppeld product: de andere regel telt
    // niet mee, precies zoals Product::getShoppingCartItemPrice() het doet.
    $eligible = percentageProduct(100.0);
    $other = percentageProduct(100.0);

    $code = percentageCode(10.0, validFor: 'products');
    $code->products()->attach($eligible->id);

    $order = orderWithCode($code, [
        ['price' => 100.0, 'product_id' => $eligible->id],
        ['price' => 100.0, 'product_id' => $other->id],
    ]);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(200.0)
        ->and(round($order->discount, 2))->toBe(10.0);
});

it('geeft een losse regel zonder product geen procentuele korting als de code aan producten gebonden is', function () {
    $eligible = percentageProduct(100.0);
    $code = percentageCode(10.0, validFor: 'products');
    $code->products()->attach($eligible->id);

    $order = orderWithCode($code, [
        ['price' => 100.0, 'product_id' => null],
    ]);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->discount, 2))->toBe(0.0);
});

it('rondt per stuk af, net als de winkelwagen', function () {
    // 3 stuks van samen 100,00 => 33,3333 per stuk. De winkelwagen rondt de
    // kortingsprijs per stuk af (30,00) en vermenigvuldigt dan pas.
    $code = percentageCode(10.0);
    $order = orderWithCode($code, [
        ['price' => 100.0, 'quantity' => 3],
    ]);

    OrderTotalsCalculator::recalculate($order);

    // 100 - (round(33.3333 * 0.9, 2) * 3) = 100 - (30.00 * 3) = 10.00
    expect(round($order->discount, 2))->toBe(10.0);
});

it('topt een herrekende procentuele korting nog steeds af op het subtotaal', function () {
    $code = percentageCode(100.0);
    $order = orderWithCode($code, [['price' => 50.0]]);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->discount, 2))->toBe(50.0)
        ->and(round($order->total, 2))->toBe(0.0);
});
