<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

it('creates an order return with a hash, requested status and requested_at', function () {
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
    ]);

    $return = OrderReturn::create([
        'order_id' => $order->id,
        'email' => 'klant@example.com',
    ]);

    expect($return->hash)->toBeString()->toHaveLength(32)
        ->and($return->status)->toBe(OrderReturn::STATUS_REQUESTED)
        ->and($return->requested_at)->not->toBeNull()
        ->and($return->order->is($order))->toBeTrue();
});

it('exposes scopes for open and requested returns', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $requested = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    $handled = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_HANDLED]);

    expect(OrderReturn::requested()->pluck('id')->all())->toBe([$requested->id])
        ->and(OrderReturn::open()->pluck('id')->all())->toContain($requested->id)
        ->and(OrderReturn::open()->pluck('id')->all())->not->toContain($handled->id);
});

it('approves a return and mirrors retour_status', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $return->approve('Akkoord, stuur maar terug');

    expect($return->fresh()->status)->toBe(OrderReturn::STATUS_APPROVED)
        ->and($return->fresh()->approved_at)->not->toBeNull()
        ->and($return->fresh()->admin_note)->toBe('Akkoord, stuur maar terug')
        ->and(OrderLog::where('order_id', $order->id)->where('tag', 'order.return-approved')->exists())->toBeTrue();
});

it('rejects a return with a reason', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $return->reject('Buiten de termijn');

    expect($return->fresh()->status)->toBe(OrderReturn::STATUS_REJECTED)
        ->and($return->fresh()->rejected_at)->not->toBeNull()
        ->and($return->fresh()->rejected_reason)->toBe('Buiten de termijn');
});

it('marks a return as handled', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $return->markHandled();

    expect($return->fresh()->status)->toBe(OrderReturn::STATUS_HANDLED)
        ->and($return->fresh()->handled_at)->not->toBeNull();
});
