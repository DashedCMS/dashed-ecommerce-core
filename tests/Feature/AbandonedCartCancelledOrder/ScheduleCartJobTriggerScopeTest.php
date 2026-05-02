<?php

use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Jobs\AbandonedCart\ScheduleAbandonedCartEmailsForCartJob;

it('only uses flows configured for cart_with_email trigger', function () {
    $cartFlow = AbandonedCartFlow::create([
        'name' => 'Cart', 'is_active' => true,
        'discount_prefix' => 'C', 'triggers' => ['cart_with_email'],
    ]);
    AbandonedCartFlowStep::create([
        'flow_id' => $cartFlow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'x', 'enabled' => true, 'blocks' => [],
    ]);

    $orderOnlyFlow = AbandonedCartFlow::create([
        'name' => 'Order', 'is_active' => true,
        'discount_prefix' => 'O', 'triggers' => ['cancelled_order'],
    ]);
    AbandonedCartFlowStep::create([
        'flow_id' => $orderOnlyFlow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'y', 'enabled' => true, 'blocks' => [],
    ]);

    $cart = Cart::create(['abandoned_email' => 'k@example.test', 'token' => 'tok-1']);
    $cart->items()->create(['quantity' => 1, 'unit_price' => 100, 'options_hash' => 'h1']);

    (new ScheduleAbandonedCartEmailsForCartJob($cart->id))->handle();

    expect(AbandonedCartEmail::where('cart_id', $cart->id)->count())->toBe(1);

    $row = AbandonedCartEmail::where('cart_id', $cart->id)->first();
    expect($row->trigger_type)->toBe('cart_with_email');
});
