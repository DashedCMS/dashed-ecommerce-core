<?php

use Illuminate\Support\Facades\Storage;
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

it('mag een geannuleerde, geretourneerde of credit-order niet in plaats aanpassen', function () {
    // canModifyInPlace() is publiek en statisch: het wijzigscherm bewaakt
    // isModifiable() wel, maar deze methode wordt ook rechtstreeks aangeroepen
    // en zou anders een afgesloten bestelling herschrijven.
    $original = Order::create(['email' => 'a@b.nl', 'status' => 'pending']);

    $cancelled = Order::create(['email' => 'a@b.nl', 'status' => 'cancelled']);
    $returned = Order::create(['email' => 'a@b.nl', 'status' => 'return']);
    $credit = Order::create(['email' => 'a@b.nl', 'status' => 'pending', 'credit_for_order_id' => $original->id]);

    expect(OrderModificationService::canModifyInPlace($cancelled))->toBeFalse()
        ->and(OrderModificationService::canModifyInPlace($returned))->toBeFalse()
        ->and(OrderModificationService::canModifyInPlace($credit))->toBeFalse();
});

it('gooit de factuur van een pending order niet weg zonder er een terug te maken', function () {
    // createInvoice() maakt alleen iets voor concepten en voor
    // paid/waiting_for_confirmation/partially_paid. Een 'pending' order —
    // toegestaan door canModifyInPlace() — raakte zijn PDF kwijt en kreeg er
    // niets voor terug.
    Storage::disk('dashed')->put('/dashed/invoices/invoice-PROFORMA-pendinghash.pdf', 'PDF');

    $order = Order::create(['email' => 'a@b.nl', 'status' => 'pending', 'invoice_id' => 'PROFORMA']);
    $order->hash = 'pendinghash';
    $order->save();
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Oud product', 'quantity' => 1, 'price' => 100, 'vat_rate' => 21]);

    OrderModificationService::applyInPlace($order->fresh(), [
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => 50.0, 'vat_rate' => 21, 'product_extras' => []],
    ], ['send_customer_email' => false]);

    expect(Storage::disk('dashed')->exists('/dashed/invoices/invoice-PROFORMA-pendinghash.pdf'))->toBeTrue();
});

it('behoudt de sku van een kostenregel bij een wijziging in plaats', function () {
    $order = conceptOrderWithLine();
    $shipping = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Verzendkosten',
        'quantity' => 1,
        'price' => 7.5,
        'vat_rate' => 21,
        'sku' => 'shipping_costs',
    ]);
    $productLine = $order->orderProducts()->where('name', 'Oud product')->first();

    OrderModificationService::applyInPlace($order->fresh(), [
        ['order_product_id' => $productLine->id, 'product_id' => null, 'name' => 'Oud product', 'quantity' => 1, 'price' => 100.0, 'vat_rate' => 21, 'product_extras' => []],
        ['order_product_id' => $shipping->id, 'product_id' => null, 'name' => 'Verzendkosten', 'quantity' => 1, 'price' => 7.5, 'vat_rate' => 21, 'product_extras' => []],
    ], ['send_customer_email' => false]);

    expect($order->fresh()->orderProducts()->where('name', 'Verzendkosten')->first()->sku)->toBe('shipping_costs');
});
