<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
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

it('haalt de korting van een procentuele code uit de regels zelf', function () {
    // Product::getShoppingCartItemPrice() verwerkt een procentuele code al per
    // regel, dus de regelprijs is de prijs ná korting en de regelkorting staat
    // ernaast. De order telt die twee bij elkaar op; hij herberekent het
    // percentage niet nog een keer.
    $code = percentageCode(10.0);
    $order = orderWithCode($code, [
        ['price' => 90.0, 'discount' => 10.0],
        ['price' => 90.0, 'discount' => 10.0],
    ], discount: 20.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(200.0)
        ->and(round($order->discount, 2))->toBe(20.0)
        ->and(round($order->total, 2))->toBe(180.0);
});

it('verhoogt het totaal met precies de regelprijs wanneer er een regel bij komt', function () {
    // De beheerder bepaalt bij een wijziging zelf wat een regel de klant kost.
    // Zou de code er alsnog een percentage af halen, dan wijkt het totaal af van
    // wat er in het scherm staat: precies de gemelde fout.
    $code = percentageCode(12.5);
    $order = orderWithCode($code, [
        ['price' => 90.0, 'discount' => 10.0],
        ['price' => 25.0],
    ], discount: 10.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->total, 2))->toBe(115.0)
        ->and(round($order->discount, 2))->toBe(10.0)
        ->and(round($order->subtotal, 2))->toBe(125.0);
});

it('bevriest een kortingscode met een vast bedrag', function () {
    // Bij een vast bedrag is bevriezen juist correct: dat bedrag is bij het
    // afrekenen afgesproken, staat niet in de regelprijzen verwerkt en beweegt
    // niet mee met de inhoud.
    $code = amountCode(15.0);
    $order = orderWithCode($code, [
        ['price' => 100.0],
        ['price' => 100.0],
    ], discount: 15.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(200.0)
        ->and(round($order->discount, 2))->toBe(15.0)
        ->and(round($order->total, 2))->toBe(185.0);
});

it('laat een order zonder kortingscode ongemoeid', function () {
    $order = orderWithCode(null, [['price' => 100.0]], discount: 25.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->discount, 2))->toBe(25.0);
});

it('geeft verzend- en betaalkosten geen korting', function () {
    // In de winkelwagen valt een procentuele code buiten de kostenregels, dus
    // die regels komen zonder eigen korting de order op en houden hem ook.
    $code = percentageCode(10.0);
    $order = orderWithCode($code, [
        ['price' => 90.0, 'discount' => 10.0],
        ['price' => 7.5, 'sku' => 'shipping_costs', 'name' => 'Verzendkosten'],
        ['price' => 5.0, 'sku' => 'payment_costs', 'name' => 'Betaalkosten'],
    ], discount: 10.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(112.5)
        ->and(round($order->discount, 2))->toBe(10.0)
        ->and(round($order->total, 2))->toBe(102.5);
});

it('topt een vaste korting af op wat er aan regels overblijft', function () {
    $code = amountCode(80.0);
    $order = orderWithCode($code, [['price' => 50.0]], discount: 80.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->discount, 2))->toBe(50.0)
        ->and(round($order->total, 2))->toBe(0.0);
});
