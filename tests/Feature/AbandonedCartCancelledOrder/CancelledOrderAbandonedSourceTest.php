<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\CancelledOrderAbandonedSource;

it('exposes email items total and variables', function () {
    $order = Order::create([
        'email' => 'z@example.test',
        'status' => 'cancelled',
        'total' => 49.95,
        'invoice_id' => '1234',
    ]);
    $order->orderProducts()->create([
        'product_id' => null,
        'name' => 'Widget',
        'quantity' => 2,
        'price' => 24.97,
    ]);

    $src = new CancelledOrderAbandonedSource($order->fresh(['orderProducts']));

    expect($src->email())->toBe('z@example.test')
        ->and($src->total())->toBe(4995)
        ->and($src->items()->count())->toBe(1)
        ->and($src->items()->first()['name'])->toBe('Widget')
        ->and($src->variables())->toHaveKey(':orderId:')
        ->and($src->variables()[':orderId:'])->toBe('1234');
});
