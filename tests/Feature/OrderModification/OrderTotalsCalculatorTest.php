<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\OrderTotalsCalculator;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function orderWithLines(array $lines, float $discount = 0.0): Order
{
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'pending', 'discount' => $discount]);

    foreach ($lines as $line) {
        OrderProduct::create([
            'order_id' => $order->id,
            'name' => $line['name'] ?? 'Regel',
            'quantity' => $line['quantity'] ?? 1,
            'price' => $line['price'],
            'vat_rate' => $line['vat_rate'],
        ]);
    }

    return $order->fresh();
}

it('telt regeltotalen op tot het subtotaal en berekent btw exclusief', function () {
    Customsetting::set('taxes_prices_include_taxes', 0);

    $order = orderWithLines([
        ['price' => 100.0, 'vat_rate' => 21],
        ['price' => 50.0, 'vat_rate' => 9],
    ]);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(150.0)
        ->and(round($order->total, 2))->toBe(150.0)
        ->and(round($order->btw, 2))->toBe(25.5)
        // toEqual (niet toBe): Order::$casts zet vat_percentages als 'array', wat
        // meteen json_encode/decode doet bij toewijzing. json_encode laat de ".0"
        // van een exact geheel bedrag (21.0 -> "21") vallen, dus 21 (int) komt terug
        // i.p.v. 21.0 (float). Dit is bestaand gedrag van Order's cast, niet van deze
        // calculator; toEqual vergelijkt op waarde i.p.v. type.
        ->and($order->vat_percentages)->toEqual(['21' => 21.0, '9' => 4.5]);
});

it('berekent btw inclusief wanneer prijzen inclusief btw zijn', function () {
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = orderWithLines([
        ['price' => 121.0, 'vat_rate' => 21],
    ]);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(121.0)
        ->and(round($order->btw, 2))->toBe(21.0);
});

it('trekt de korting van het totaal af en schaalt de btw mee', function () {
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = orderWithLines([
        ['price' => 121.0, 'vat_rate' => 21],
    ], discount: 12.1);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(121.0)
        ->and(round($order->total, 2))->toBe(108.9)
        ->and(round($order->btw, 2))->toBe(18.9)
        ->and($order->vat_percentages)->toBe(['21' => 18.9]);
});

it('topt een korting die groter is dan het subtotaal af', function () {
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = orderWithLines([
        ['price' => 24.2, 'vat_rate' => 21],
    ], discount: 40.0);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(24.2)
        ->and(round($order->discount, 2))->toBe(24.2)
        ->and(round($order->total, 2))->toBe(0.0)
        ->and(round($order->btw, 2))->toBe(0.0);
});

it('zet alles op nul voor een order zonder regels', function () {
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = orderWithLines([]);

    OrderTotalsCalculator::recalculate($order);

    expect(round($order->subtotal, 2))->toBe(0.0)
        ->and(round($order->total, 2))->toBe(0.0)
        ->and(round($order->btw, 2))->toBe(0.0)
        ->and($order->vat_percentages)->toBe([]);
});
