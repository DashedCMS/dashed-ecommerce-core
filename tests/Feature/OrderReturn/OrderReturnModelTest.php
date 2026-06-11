<?php

use Dashed\DashedEcommerceCore\Models\Order;
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
