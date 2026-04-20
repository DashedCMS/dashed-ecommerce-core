<?php

use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;

function makeFlowStep(array $overrides = []): AbandonedCartFlowStep
{
    $flow = AbandonedCartFlow::create([
        'name' => 'Test flow',
        'is_active' => true,
        'discount_prefix' => 'TST',
    ]);

    return AbandonedCartFlowStep::create(array_merge([
        'flow_id' => $flow->id,
        'sort_order' => 1,
        'delay_value' => 1,
        'delay_unit' => 'hours',
        'subject' => ['nl' => 'Onderwerp'],
        'blocks' => [],
        'incentive_enabled' => false,
        'incentive_type' => 'percentage',
        'incentive_value' => 0,
        'incentive_valid_days' => 0,
        'enabled' => true,
    ], $overrides));
}

function makeCart(): Cart
{
    return Cart::create(['locale' => 'nl']);
}

function makeConvertedEmail(AbandonedCartFlowStep $step, float $orderTotal): AbandonedCartEmail
{
    $cart = makeCart();
    $order = Order::withoutEvents(function () use ($cart, $orderTotal) {
        return Order::create([
            'cart_id' => $cart->id,
            'total' => $orderTotal,
            'status' => 'paid',
            'email' => 'klant@example.com',
            'site_id' => 'default',
            'ip' => '127.0.0.1',
            'hash' => bin2hex(random_bytes(8)),
        ]);
    });

    return AbandonedCartEmail::create([
        'cart_id' => $cart->id,
        'flow_step_id' => $step->id,
        'email' => 'klant@example.com',
        'email_number' => 1,
        'sent_at' => now()->subHour(),
        'converted_at' => now(),
        'order_id' => $order->id,
    ]);
}

// Task 1: revenueSum()

it('sums revenue across converted emails for a flow step', function () {
    $step = makeFlowStep();
    makeConvertedEmail($step, 49.95);
    makeConvertedEmail($step, 120.00);

    // Pending email (no converted_at, no order_id) - should not be counted
    AbandonedCartEmail::create([
        'cart_id' => makeCart()->id,
        'flow_step_id' => $step->id,
        'email' => 'ander@example.com',
        'email_number' => 1,
    ]);

    expect($step->revenueSum())->toEqualWithDelta(169.95, 0.001);
});

it('returns zero revenue when no conversions exist', function () {
    $step = makeFlowStep();

    expect($step->revenueSum())->toBe(0.0);
});

// Task 2: conversionRateFromSent()

it('calculates conversion rate based on sent emails', function () {
    $step = makeFlowStep();

    // 3 sent, 1 converted = 33.3%
    makeConvertedEmail($step, 50.00);

    AbandonedCartEmail::create([
        'cart_id' => makeCart()->id,
        'flow_step_id' => $step->id,
        'email' => 'a@example.com',
        'email_number' => 1,
        'sent_at' => now()->subMinutes(30),
    ]);
    AbandonedCartEmail::create([
        'cart_id' => makeCart()->id,
        'flow_step_id' => $step->id,
        'email' => 'b@example.com',
        'email_number' => 1,
        'sent_at' => now()->subMinutes(15),
    ]);

    expect($step->conversionRateFromSent())->toEqualWithDelta(33.3, 0.1);
});

it('returns zero conversion rate when no emails sent', function () {
    $step = makeFlowStep();

    expect($step->conversionRateFromSent())->toBe(0.0);
});

// Task 3: averageConversionHours()

it('returns average hours between sent and converted', function () {
    $step = makeFlowStep();

    $cartA = makeCart();
    $orderA = Order::withoutEvents(function () use ($cartA) {
        return Order::create([
            'cart_id' => $cartA->id,
            'total' => 100.0,
            'status' => 'paid',
            'email' => 'x@example.com',
            'site_id' => 'default',
            'ip' => '127.0.0.1',
            'hash' => bin2hex(random_bytes(8)),
        ]);
    });
    AbandonedCartEmail::create([
        'cart_id' => $cartA->id,
        'flow_step_id' => $step->id,
        'email' => 'x@example.com',
        'email_number' => 1,
        'sent_at' => now()->subHours(8),
        'converted_at' => now()->subHours(6),
        'order_id' => $orderA->id,
    ]);

    $cartB = makeCart();
    $orderB = Order::withoutEvents(function () use ($cartB) {
        return Order::create([
            'cart_id' => $cartB->id,
            'total' => 50.0,
            'status' => 'paid',
            'email' => 'y@example.com',
            'site_id' => 'default',
            'ip' => '127.0.0.1',
            'hash' => bin2hex(random_bytes(8)),
        ]);
    });
    AbandonedCartEmail::create([
        'cart_id' => $cartB->id,
        'flow_step_id' => $step->id,
        'email' => 'y@example.com',
        'email_number' => 1,
        'sent_at' => now()->subHours(4),
        'converted_at' => now(),
        'order_id' => $orderB->id,
    ]);

    expect($step->averageConversionHours())->toEqualWithDelta(3.0, 0.1);
});

it('returns null when no conversions exist', function () {
    $step = makeFlowStep();

    expect($step->averageConversionHours())->toBeNull();
});

// Task 4: AbandonedCartFlow aggregate methods

it('aggregates recovery rate across all steps in a flow', function () {
    $flow = AbandonedCartFlow::create([
        'name' => 'Flow A',
        'is_active' => true,
        'discount_prefix' => 'FLW',
    ]);
    $step1 = AbandonedCartFlowStep::create([
        'flow_id' => $flow->id,
        'sort_order' => 1,
        'delay_value' => 1,
        'delay_unit' => 'hours',
        'subject' => ['nl' => 'Step 1'],
        'blocks' => [],
        'incentive_enabled' => false,
        'incentive_type' => 'percentage',
        'incentive_value' => 0,
        'incentive_valid_days' => 0,
        'enabled' => true,
    ]);
    $step2 = AbandonedCartFlowStep::create([
        'flow_id' => $flow->id,
        'sort_order' => 2,
        'delay_value' => 24,
        'delay_unit' => 'hours',
        'subject' => ['nl' => 'Step 2'],
        'blocks' => [],
        'incentive_enabled' => false,
        'incentive_type' => 'percentage',
        'incentive_value' => 0,
        'incentive_valid_days' => 0,
        'enabled' => true,
    ]);

    // Step 1: 2 sent, 1 converted. Step 2: 2 sent, 1 converted. Total 4 sent, 2 converted = 50%.
    makeConvertedEmail($step1, 40.0);
    AbandonedCartEmail::create([
        'cart_id' => makeCart()->id,
        'flow_step_id' => $step1->id,
        'email' => 'a@example.com',
        'email_number' => 1,
        'sent_at' => now()->subMinutes(30),
    ]);
    makeConvertedEmail($step2, 60.0);
    AbandonedCartEmail::create([
        'cart_id' => makeCart()->id,
        'flow_step_id' => $step2->id,
        'email' => 'b@example.com',
        'email_number' => 1,
        'sent_at' => now()->subMinutes(15),
    ]);

    expect($flow->recoveryRate())->toEqualWithDelta(50.0, 0.1)
        ->and($flow->revenueSum())->toEqualWithDelta(100.0, 0.001);
});
