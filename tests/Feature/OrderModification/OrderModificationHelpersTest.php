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

it('mag een order alleen wijzigen als hij niet geannuleerd, geretourneerd, vervangen of een creditnota is', function () {
    $ordinary = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $cancelled = Order::create(['email' => 'a@b.nl', 'status' => 'cancelled']);
    $returned = Order::create(['email' => 'a@b.nl', 'status' => 'return']);

    // Bewust status 'paid': een gecrediteerd-en-vervangen order blijft op
    // 'paid' staan (zie OrderModificationService::creditOldOrder()) en wordt
    // dan alléén door replaced_by_order_id geblokkeerd. Met status
    // 'cancelled' hier zou een predicate die alleen naar status kijkt deze
    // case ook laten slagen, zonder replaced_by_order_id ooit te toetsen.
    $replacement = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $replaced = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'replaced_by_order_id' => $replacement->id]);

    $original = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $credit = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'credit_for_order_id' => $original->id]);

    expect($ordinary->isModifiable())->toBeTrue()
        ->and($cancelled->isModifiable())->toBeFalse()
        ->and($returned->isModifiable())->toBeFalse()
        ->and($replaced->isModifiable())->toBeFalse()
        ->and($credit->isModifiable())->toBeFalse();
});
