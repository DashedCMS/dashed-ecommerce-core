<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function conceptOrderWithLine(): Order
{
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'total' => 100, 'subtotal' => 100]);
    OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Oud product',
        'quantity' => 1,
        'price' => 100,
        'vat_rate' => 21,
    ]);

    return $order->fresh();
}

it('mag een concept in plaats aanpassen', function () {
    expect(OrderModificationService::canModifyInPlace(conceptOrderWithLine()))->toBeTrue();
});

it('mag een order met een echte factuur niet in plaats aanpassen', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'pending', 'invoice_id' => '2026-0001']);

    expect(OrderModificationService::canModifyInPlace($order))->toBeFalse();
});

it('mag een betaalde order niet in plaats aanpassen', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);

    expect(OrderModificationService::canModifyInPlace($order))->toBeFalse();
});

it('mag een order met een betaalde betaling niet in plaats aanpassen', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'pending']);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 10, 'psp' => 'own']);

    expect(OrderModificationService::canModifyInPlace($order->fresh()))->toBeFalse();
});

it('mag een al vervangen order niet in plaats aanpassen', function () {
    $replacement = Order::create(['email' => 'a@b.nl', 'status' => 'pending']);
    $order = Order::create([
        'email' => 'a@b.nl',
        'status' => 'cancelled',
        'replaced_by_order_id' => $replacement->id,
    ]);

    expect(OrderModificationService::canModifyInPlace($order))->toBeFalse();
});

it('vervangt de regels en herberekent de totalen', function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    $order = conceptOrderWithLine();

    OrderModificationService::applyInPlace($order, [
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Nieuw product', 'quantity' => 2, 'price' => 121.0, 'vat_rate' => 21, 'product_extras' => []],
    ]);

    $order = $order->fresh();
    $lines = $order->orderProducts()->get();

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->name)->toBe('Nieuw product')
        ->and((int) $lines[0]->quantity)->toBe(2)
        ->and(round($order->total, 2))->toBe(121.0)
        ->and(round($order->btw, 2))->toBe(21.0);
});

it('laat geen zwevende oude regels achter', function () {
    $order = conceptOrderWithLine();

    OrderModificationService::applyInPlace($order, [
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => 50.0, 'vat_rate' => 21, 'product_extras' => []],
    ]);

    expect(OrderProduct::withTrashed()->where('order_id', $order->id)->count())->toBe(1);
});

it('weigert in plaats aanpassen van een betaalde order', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);

    expect(fn () => OrderModificationService::applyInPlace($order, []))->toThrow(LogicException::class);
});
