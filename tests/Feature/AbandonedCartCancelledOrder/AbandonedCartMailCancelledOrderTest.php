<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Mail\AbandonedCartMail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;

it('renders items from cancelled order via resolver', function () {
    $order = Order::create([
        'email' => 'q@example.test',
        'status' => 'cancelled',
        'total' => 19.95,
        'invoice_id' => '9001',
    ]);
    $order->orderProducts()->create(['name' => 'Frisbee', 'quantity' => 1, 'price' => 19.95]);

    $flow = AbandonedCartFlow::create([
        'name' => 'F', 'is_active' => true, 'discount_prefix' => 'P', 'triggers' => ['cancelled_order'],
    ]);
    $step = AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'Mis je bestelling :orderId:?', 'enabled' => true,
        'blocks' => [
            ['type' => 'text', 'data' => ['content' => '<p>Order :orderId:</p>']],
            ['type' => 'products', 'data' => []],
        ],
    ]);

    $record = AbandonedCartEmail::create([
        'email' => 'q@example.test',
        'trigger_type' => 'cancelled_order',
        'cancelled_order_id' => $order->id,
        'email_number' => 1,
        'flow_step_id' => $step->id,
        'send_at' => now(),
    ]);

    $mailable = new AbandonedCartMail($record, $step, null, 'nl', $record->id);
    $rendered = $mailable->render();

    expect($rendered)->toContain('Frisbee')
        ->and($rendered)->toContain('Order 9001');
});
