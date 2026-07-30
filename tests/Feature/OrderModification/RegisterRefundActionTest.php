<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Actions\RegisterRefundAction;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function overpaidOrder(): Order
{
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'total' => 80]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);

    return $order->fresh();
}

it('boekt een terugstorting als negatieve betaling', function () {
    $order = overpaidOrder();

    (new RegisterRefundAction())->handle($order, ['amount' => 20]);

    $order = $order->fresh();

    expect(round($order->overpaidAmount(), 2))->toBe(0.0)
        ->and(round((float) $order->orderPayments()->where('status', 'paid')->sum('amount'), 2))->toBe(80.0)
        ->and($order->orderPayments()->where('payment_method', 'refund')->count())->toBe(1);
});

it('weigert een bedrag boven het teveel betaalde', function () {
    $order = overpaidOrder();

    expect(fn () => (new RegisterRefundAction())->handle($order, ['amount' => 25]))
        ->toThrow(InvalidArgumentException::class);
});

it('weigert een bedrag van nul of lager', function () {
    $order = overpaidOrder();

    expect(fn () => (new RegisterRefundAction())->handle($order, ['amount' => 0]))
        ->toThrow(InvalidArgumentException::class);
});
