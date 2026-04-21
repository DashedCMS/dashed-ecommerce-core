<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Illuminate\Support\Facades\Mail;

it('cancelling an unpaid order schedules emails and sends them when due', function () {
    Mail::fake();

    $flow = AbandonedCartFlow::create([
        'name' => 'E2E', 'is_active' => true,
        'discount_prefix' => 'P', 'triggers' => ['cancelled_order'],
    ]);
    $step = AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'Herstel je bestelling :orderId:',
        'enabled' => true,
        'blocks' => [['type' => 'text', 'data' => ['content' => '<p>Hoi</p>']]],
    ]);

    $order = Order::create([
        'email' => 'e@example.test',
        'status' => 'pending',
        'total' => 30,
        'invoice_id' => '7777',
    ]);
    $order->orderProducts()->create(['name' => 'Thing', 'quantity' => 1, 'price' => 30]);

    $order->markAsCancelled();

    expect(AbandonedCartEmail::where('cancelled_order_id', $order->id)->count())->toBe(1);

    $this->travel(2)->hours();

    $this->artisan('dashed:send-abandoned-cart-emails');

    $row = AbandonedCartEmail::where('cancelled_order_id', $order->id)->first();
    expect($row->sent_at)->not->toBeNull();
    Mail::assertSent(\Dashed\DashedEcommerceCore\Mail\AbandonedCartMail::class);
});

it('paying another order cancels the scheduled emails', function () {
    $flow = AbandonedCartFlow::create([
        'name' => 'E2E2', 'is_active' => true,
        'discount_prefix' => 'P', 'triggers' => ['cancelled_order'],
    ]);
    AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'x', 'enabled' => true, 'blocks' => [],
    ]);

    $cancelled = Order::create(['email' => 's@example.test', 'status' => 'pending']);
    $cancelled->markAsCancelled();

    expect(AbandonedCartEmail::where('cancelled_order_id', $cancelled->id)->count())->toBe(1);

    $newOrder = Order::create(['email' => 's@example.test', 'status' => 'paid']);
    OrderMarkedAsPaidEvent::dispatch($newOrder);

    $row = AbandonedCartEmail::where('cancelled_order_id', $cancelled->id)->first();
    expect($row->cancelled_at)->not->toBeNull()
        ->and($row->cancelled_reason)->toBe('converted');
});
