<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;

function makeStep(): AbandonedCartFlowStep
{
    $flow = AbandonedCartFlow::create([
        'name' => 'F', 'is_active' => true,
        'discount_prefix' => 'P', 'triggers' => ['cancelled_order'],
    ]);

    return AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'x', 'enabled' => true, 'blocks' => [],
    ]);
}

it('cancels cancelled_order row when order is no longer cancelled (was paid)', function () {
    $step = makeStep();

    $order = Order::create(['email' => 'x@example.test', 'status' => 'paid']);

    $row = AbandonedCartEmail::create([
        'email' => 'x@example.test',
        'trigger_type' => 'cancelled_order',
        'cancelled_order_id' => $order->id,
        'email_number' => 1,
        'flow_step_id' => $step->id,
        'send_at' => now()->subMinute(),
    ]);

    $this->artisan('dashed:send-abandoned-cart-emails');

    expect($row->fresh()->cancelled_at)->not->toBeNull()
        ->and($row->fresh()->cancelled_reason)->toBe('source_recovered');
});

it('cancels cancelled_order row when order is missing', function () {
    $step = makeStep();

    $row = AbandonedCartEmail::create([
        'email' => 'y@example.test',
        'trigger_type' => 'cancelled_order',
        'cancelled_order_id' => null,
        'email_number' => 1,
        'flow_step_id' => $step->id,
        'send_at' => now()->subMinute(),
    ]);

    $this->artisan('dashed:send-abandoned-cart-emails');

    expect($row->fresh()->cancelled_at)->not->toBeNull()
        ->and($row->fresh()->cancelled_reason)->toBe('source_empty');
});
