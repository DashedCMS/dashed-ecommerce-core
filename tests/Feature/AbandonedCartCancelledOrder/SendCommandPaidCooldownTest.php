<?php

use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Mail\AbandonedCartMail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;

function cooldownRow(?int $cooldownDays, string $email): AbandonedCartEmail
{
    $flow = AbandonedCartFlow::create([
        'name' => 'Cooldown', 'is_active' => true, 'discount_prefix' => 'P',
        'triggers' => ['cancelled_order'], 'skip_if_paid_within_days' => $cooldownDays,
    ]);
    $step = AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'x', 'enabled' => true,
        'blocks' => [['type' => 'text', 'data' => ['content' => '<p>Hoi</p>']]],
    ]);

    $abandoned = Order::create(['email' => $email, 'status' => 'cancelled']);
    $abandoned->orderProducts()->create([
        'product_id' => makeMobileProduct(['slug' => ['nl' => 'cooldown-'.md5($email)]])->id,
        'name' => 'Ding', 'quantity' => 1, 'price' => 10,
    ]);

    return AbandonedCartEmail::create([
        'email' => $email,
        'trigger_type' => 'cancelled_order',
        'cancelled_order_id' => $abandoned->id,
        'email_number' => 1,
        'flow_step_id' => $step->id,
        'send_at' => now()->subMinute(),
    ]);
}

it('skips and cancels when the recipient paid an order within the flow cooldown', function () {
    Mail::fake();

    $row = cooldownRow(30, 'cool@example.test');
    $second = AbandonedCartEmail::create([
        'email' => 'cool@example.test', 'trigger_type' => 'cancelled_order',
        'cancelled_order_id' => $row->cancelled_order_id, 'email_number' => 2,
        'flow_step_id' => $row->flow_step_id, 'send_at' => now()->addDay(),
    ]);

    $paid = Order::create(['email' => 'cool@example.test', 'status' => 'paid']);
    $paid->forceFill(['created_at' => now()->subDays(10)])->save();

    $this->artisan('dashed:send-abandoned-cart-emails');

    Mail::assertNotSent(AbandonedCartMail::class);
    expect($row->fresh()->cancelled_reason)->toBe('recent_paid_order')
        ->and($second->fresh()->cancelled_reason)->toBe('recent_paid_order');
});

it('sends when the paid order is older than the cooldown', function () {
    Mail::fake();

    $row = cooldownRow(30, 'old@example.test');
    $paid = Order::create(['email' => 'old@example.test', 'status' => 'paid']);
    $paid->forceFill(['created_at' => now()->subDays(40)])->save();

    $this->artisan('dashed:send-abandoned-cart-emails');

    expect($row->fresh()->sent_at)->not->toBeNull();
});

it('ignores recent paid orders when the cooldown is off', function () {
    Mail::fake();

    $row = cooldownRow(null, 'off@example.test');
    $paid = Order::create(['email' => 'off@example.test', 'status' => 'paid']);
    $paid->forceFill(['created_at' => now()->subDays(2)])->save();

    $this->artisan('dashed:send-abandoned-cart-emails');

    expect($row->fresh()->sent_at)->not->toBeNull();
});
