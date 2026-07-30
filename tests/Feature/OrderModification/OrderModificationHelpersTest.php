<?php

use Dashed\DashedEcommerceCore\Models\Order;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('herkent een echte factuur', function () {
    expect(Order::create(['email' => 'a@b.nl', 'invoice_id' => '2026-0001'])->hasRealInvoice())->toBeTrue()
        ->and(Order::create(['email' => 'a@b.nl', 'invoice_id' => 'PROFORMA'])->hasRealInvoice())->toBeFalse()
        ->and(Order::create(['email' => 'a@b.nl', 'invoice_id' => 'RETURN'])->hasRealInvoice())->toBeFalse()
        ->and(Order::create(['email' => 'a@b.nl', 'invoice_id' => null])->hasRealInvoice())->toBeFalse();
});

it('berekent het teveel betaalde bedrag', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'total' => 80]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);

    expect(round($order->overpaidAmount(), 2))->toBe(20.0)
        ->and(round($order->outstandingAmount(), 2))->toBe(0.0);
});

it('geeft geen teveel betaald bedrag als er precies genoeg betaald is', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'total' => 100]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);

    expect(round($order->overpaidAmount(), 2))->toBe(0.0);
});

it('koppelt een order aan zijn vervanger en terug', function () {
    $old = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $new = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);

    $old->replaced_by_order_id = $new->id;
    $old->save();

    expect($old->fresh()->replacedByOrder->id)->toBe($new->id)
        ->and($new->fresh()->replacesOrder->id)->toBe($old->id);
});
