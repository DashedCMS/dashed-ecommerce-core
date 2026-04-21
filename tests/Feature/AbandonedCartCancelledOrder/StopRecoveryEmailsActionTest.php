<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

it('helper cancels pending rows for order email with manual_admin reason', function () {
    $order = Order::create(['email' => 'm@example.test', 'status' => 'cancelled']);
    $pending = AbandonedCartEmail::create([
        'email' => 'm@example.test',
        'trigger_type' => 'cancelled_order',
        'cancelled_order_id' => $order->id,
        'email_number' => 1,
        'flow_step_id' => null,
        'send_at' => now()->addHour(),
    ]);

    $count = AbandonedCartEmail::cancelPendingForEmail($order->email, 'manual_admin');

    expect($count)->toBe(1)
        ->and($pending->fresh()->cancelled_reason)->toBe('manual_admin');
});
