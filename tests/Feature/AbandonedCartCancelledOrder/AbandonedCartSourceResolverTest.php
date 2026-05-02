<?php

use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\CartAbandonedSource;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\AbandonedCartSourceResolver;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\CancelledOrderAbandonedSource;

it('returns cart source for cart_with_email trigger', function () {
    $cart = Cart::create(['token' => 'tok-resolver']);
    $record = AbandonedCartEmail::create([
        'email' => 'a@b.c',
        'trigger_type' => 'cart_with_email',
        'cart_id' => $cart->id,
        'email_number' => 1,
        'flow_step_id' => null,
        'send_at' => now(),
    ]);

    expect(AbandonedCartSourceResolver::for($record))->toBeInstanceOf(CartAbandonedSource::class);
});

it('returns order source for cancelled_order trigger', function () {
    $order = Order::create(['email' => 'a@b.c', 'status' => 'cancelled']);
    $record = AbandonedCartEmail::create([
        'email' => 'a@b.c',
        'trigger_type' => 'cancelled_order',
        'cancelled_order_id' => $order->id,
        'email_number' => 1,
        'flow_step_id' => null,
        'send_at' => now(),
    ]);

    expect(AbandonedCartSourceResolver::for($record))->toBeInstanceOf(CancelledOrderAbandonedSource::class);
});
