<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Listeners\AbandonedCart\CancelPendingAbandonedEmailsListener;

it('cancels pending rows for both trigger types matching email', function () {
    $cartPending = AbandonedCartEmail::create([
        'email' => 'buy@example.test',
        'trigger_type' => 'cart_with_email',
        'email_number' => 1,
        'flow_step_id' => null,
        'send_at' => now()->addHour(),
    ]);
    $cancelPending = AbandonedCartEmail::create([
        'email' => 'buy@example.test',
        'trigger_type' => 'cancelled_order',
        'email_number' => 1,
        'flow_step_id' => null,
        'send_at' => now()->addHour(),
    ]);
    $otherEmail = AbandonedCartEmail::create([
        'email' => 'other@example.test',
        'trigger_type' => 'cart_with_email',
        'email_number' => 1,
        'flow_step_id' => null,
        'send_at' => now()->addHour(),
    ]);

    $order = Order::create(['email' => 'buy@example.test', 'status' => 'paid']);

    (new CancelPendingAbandonedEmailsListener())
        ->handle(new OrderMarkedAsPaidEvent($order));

    expect($cartPending->fresh()->cancelled_at)->not->toBeNull()
        ->and($cartPending->fresh()->cancelled_reason)->toBe('converted')
        ->and($cancelPending->fresh()->cancelled_at)->not->toBeNull()
        ->and($otherEmail->fresh()->cancelled_at)->toBeNull();
});

it('noops when order has no email', function () {
    $pending = AbandonedCartEmail::create([
        'email' => 'buy@example.test',
        'trigger_type' => 'cart_with_email',
        'email_number' => 1,
        'flow_step_id' => null,
        'send_at' => now()->addHour(),
    ]);

    $order = Order::create(['email' => null, 'status' => 'paid']);

    (new CancelPendingAbandonedEmailsListener())
        ->handle(new OrderMarkedAsPaidEvent($order));

    expect($pending->fresh()->cancelled_at)->toBeNull();
});
