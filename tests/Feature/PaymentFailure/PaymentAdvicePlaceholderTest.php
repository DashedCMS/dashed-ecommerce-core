<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Mail\AbandonedCartMail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\CancelledOrderAbandonedSource;

function orderMetStaat(string $staat): Order
{
    $order = Order::create(['email' => 'p@example.test', 'status' => 'cancelled', 'total' => 30]);
    OrderPayment::create([
        'order_id' => $order->id, 'psp' => 'paynl', 'psp_id' => uniqid(),
        'payment_method' => 'Riverty – Achteraf betalen', 'amount' => 30, 'status' => 'cancelled',
        'attributes' => ['psp_state' => $staat],
    ]);

    return $order;
}

it('adviseert iDEAL met de naam van de methode bij een afwijzing', function () {
    $advies = (new CancelledOrderAbandonedSource(orderMetStaat('DENIED_63')))->variables()[':paymentAdvice:'];

    expect($advies)->toContain('Riverty – Achteraf betalen')
        ->and($advies)->toContain('niet geaccepteerd')
        ->and($advies)->toContain('iDEAL');
});

it('adviseert alsnog afronden bij een afgebroken betaling', function () {
    $advies = (new CancelledOrderAbandonedSource(orderMetStaat('CANCEL')))->variables()[':paymentAdvice:'];

    expect($advies)->toContain('alsnog afronden')
        ->and($advies)->not->toContain('Riverty');
});

it('vult de plaatshouder in de mailtekst en laat hem leeg voor een verlaten wagen', function () {
    $flow = AbandonedCartFlow::create(['name' => 'B', 'is_active' => true, 'discount_prefix' => 'TERUG', 'triggers' => ['cancelled_order']]);
    $step = AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1, 'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'x', 'enabled' => true,
        'blocks' => [['type' => 'text', 'data' => ['content' => '<p>Bewaard. :paymentAdvice:</p>']]],
    ]);

    $order = orderMetStaat('DENIED_63');
    $record = AbandonedCartEmail::create([
        'email' => 'p@example.test', 'trigger_type' => 'cancelled_order', 'cancelled_order_id' => $order->id,
        'email_number' => 1, 'flow_step_id' => $step->id, 'send_at' => now(),
    ]);
    $gerenderd = (new AbandonedCartMail($record, $step, null, 'nl'))->render();

    expect($gerenderd)->toContain('Bewaard. Achteraf betalen via Riverty')
        ->and($gerenderd)->not->toContain(':paymentAdvice:');

    $cart = \Dashed\DashedEcommerceCore\Models\Cart::create(['abandoned_email' => 'w@example.test', 'locale' => 'nl', 'token' => uniqid(), 'total' => 30.00]);
    $wagenRecord = AbandonedCartEmail::create([
        'email' => 'w@example.test', 'trigger_type' => 'cart_with_email', 'cart_id' => $cart->id,
        'email_number' => 1, 'flow_step_id' => $step->id, 'send_at' => now(),
    ]);
    $gerenderdWagen = (new AbandonedCartMail($wagenRecord, $step, null, 'nl'))->render();

    expect($gerenderdWagen)->toContain('Bewaard.')
        ->and($gerenderdWagen)->not->toContain(':paymentAdvice:');
});
