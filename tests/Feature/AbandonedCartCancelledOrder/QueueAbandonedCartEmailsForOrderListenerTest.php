<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;
use Dashed\DashedEcommerceCore\Listeners\AbandonedCart\QueueAbandonedCartEmailsForOrderListener;

function makeFlowWithStep(array $triggers, bool $isActive = true): AbandonedCartFlow
{
    $flow = AbandonedCartFlow::create([
        'name' => 'Flow',
        'is_active' => $isActive,
        'discount_prefix' => 'X',
        'triggers' => $triggers,
    ]);
    AbandonedCartFlowStep::create([
        'flow_id' => $flow->id,
        'sort_order' => 1,
        'delay_value' => 1,
        'delay_unit' => 'hours',
        'subject' => 'Test',
        'enabled' => true,
        'blocks' => [],
    ]);

    return $flow;
}

it('schedules one email per enabled step for cancelled_order trigger', function () {
    $flow = AbandonedCartFlow::create([
        'name' => 'Cancel flow',
        'is_active' => true,
        'discount_prefix' => 'COMEBACK',
        'triggers' => ['cancelled_order'],
    ]);
    AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'Test', 'enabled' => true, 'blocks' => [],
    ]);
    AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 2,
        'delay_value' => 24, 'delay_unit' => 'hours',
        'subject' => 'Test 2', 'enabled' => true, 'blocks' => [],
    ]);

    $order = Order::create(['email' => 'k@example.test', 'status' => 'cancelled']);

    (new QueueAbandonedCartEmailsForOrderListener())
        ->handle(new OrderCancelledEvent($order));

    expect(AbandonedCartEmail::where('cancelled_order_id', $order->id)->count())->toBe(2);

    $rows = AbandonedCartEmail::where('cancelled_order_id', $order->id)->get();
    expect($rows->every(fn ($r) => $r->trigger_type === 'cancelled_order' && $r->cart_id === null))->toBeTrue();
});

it('skips when order has no email', function () {
    $order = Order::create(['email' => null, 'status' => 'cancelled']);
    makeFlowWithStep(['cancelled_order']);

    (new QueueAbandonedCartEmailsForOrderListener())
        ->handle(new OrderCancelledEvent($order));

    expect(AbandonedCartEmail::count())->toBe(0);
});

it('skips when order was ever paid', function () {
    $order = Order::create(['email' => 'k@example.test', 'status' => 'cancelled']);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 1000, 'psp' => 'test']);
    makeFlowWithStep(['cancelled_order']);

    (new QueueAbandonedCartEmailsForOrderListener())
        ->handle(new OrderCancelledEvent($order));

    expect(AbandonedCartEmail::count())->toBe(0);
});

it('skips flows without cancelled_order trigger', function () {
    $order = Order::create(['email' => 'k@example.test', 'status' => 'cancelled']);
    makeFlowWithStep(['cart_with_email']);

    (new QueueAbandonedCartEmailsForOrderListener())
        ->handle(new OrderCancelledEvent($order));

    expect(AbandonedCartEmail::count())->toBe(0);
});

it('skips inactive flows', function () {
    $order = Order::create(['email' => 'k@example.test', 'status' => 'cancelled']);
    makeFlowWithStep(['cancelled_order'], isActive: false);

    (new QueueAbandonedCartEmailsForOrderListener())
        ->handle(new OrderCancelledEvent($order));

    expect(AbandonedCartEmail::count())->toBe(0);
});
