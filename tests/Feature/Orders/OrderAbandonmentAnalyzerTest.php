<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Services\Orders\OrderAbandonmentAnalyzer;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeOrder(string $status, float $total = 50.0): Order
{
    return Order::create([
        'invoice_id' => 'INV-'.uniqid(),
        'status' => $status,
        'order_origin' => 'own',
        'total' => $total,
    ]);
}

it('returns null for a fully paid order', function () {
    $order = makeOrder('paid');

    expect((new OrderAbandonmentAnalyzer())->analyze($order))->toBeNull();
});

it('flags a cancelled order', function () {
    $order = makeOrder('cancelled');

    $d = (new OrderAbandonmentAnalyzer())->analyze($order);

    expect($d?->cause)->toBe('cancelled');
    expect($d?->confidence)->toBe('high');
});

it('flags a partially paid order with the open amount', function () {
    $order = makeOrder('partially_paid', 100.0);
    OrderPayment::create([
        'order_id' => $order->id,
        'psp' => 'mollie',
        'amount' => 40.0,
        'status' => 'paid',
    ]);

    $d = (new OrderAbandonmentAnalyzer())->analyze($order->fresh());

    expect($d?->cause)->toBe('partial_payment');
});

it('flags an order waiting for manual confirmation', function () {
    $order = makeOrder('waiting_for_confirmation');

    expect((new OrderAbandonmentAnalyzer())->analyze($order)?->cause)
        ->toBe('awaiting_manual_payment');
});

it('flags a failed payment start from the order log', function () {
    $order = makeOrder('pending');
    $order->logs()->create([
        'tag' => 'order.payment-start.failed',
        'note' => 'Mollie: The amount is higher than the maximum',
    ]);

    $d = (new OrderAbandonmentAnalyzer())->analyze($order->fresh());

    expect($d?->cause)->toBe('payment_start_failed');
    expect($d?->confidence)->toBe('high');
    expect(implode(' ', $d->evidence))->toContain('Mollie');
});

it('flags an order abandoned at the payment provider', function () {
    $order = makeOrder('pending');
    OrderPayment::create([
        'order_id' => $order->id,
        'psp' => 'mollie',
        'psp_id' => 'tr_abc123',
        'amount' => 50.0,
        'status' => 'pending',
    ]);

    expect((new OrderAbandonmentAnalyzer())->analyze($order->fresh())?->cause)
        ->toBe('abandoned_at_psp');
});

it('flags an order with no payment attempt at all', function () {
    $order = makeOrder('pending');

    expect((new OrderAbandonmentAnalyzer())->analyze($order->fresh())?->cause)
        ->toBe('no_payment_attempt');
});
