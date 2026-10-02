<?php

use Illuminate\Support\Facades\URL;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartClick;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\CancelledOrderAbandonedSource;

function maakHerstelmail(): array
{
    $order = Order::create(['email' => 'h@example.test', 'status' => 'cancelled', 'total' => 30]);
    OrderPayment::create([
        'order_id' => $order->id, 'psp' => 'paynl', 'psp_id' => uniqid(),
        'payment_method' => 'Riverty – Achteraf betalen', 'amount' => 30, 'status' => 'cancelled',
        'attributes' => ['psp_state' => 'DENIED_63'],
    ]);
    $flow = AbandonedCartFlow::create(['name' => 'Betaling', 'is_active' => true, 'discount_prefix' => 'TERUG', 'triggers' => ['cancelled_order']]);
    $step = AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1, 'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => 'x', 'enabled' => true, 'blocks' => [],
    ]);
    $record = AbandonedCartEmail::create([
        'email' => 'h@example.test', 'trigger_type' => 'cancelled_order', 'cancelled_order_id' => $order->id,
        'email_number' => 1, 'flow_step_id' => $step->id, 'send_at' => now(), 'sent_at' => now(),
    ]);

    // Precies zoals AbandonedCartMail de knop-URL bouwt: eerst ondertekenen, dan email_id erachter.
    $url = (new CancelledOrderAbandonedSource($order))->resumeUrl() . '&email_id=' . $record->id;

    return [$order, $record, $url];
}

it('accepteert de knop-URL uit de mail, registreert de klik en geeft de afwijzing door', function () {
    [$order, $record, $url] = maakHerstelmail();

    $response = $this->get($url);

    $response->assertRedirect();
    $response->assertSessionHas('cancelled_order_id', $order->id);
    $response->assertSessionHas('payment_declined', true);
    expect($record->fresh()->clicked_at)->not->toBeNull()
        ->and(AbandonedCartClick::where('abandoned_cart_email_id', $record->id)->count())->toBe(1)
        ->and(session()->get('abandoned_cart_recovery'))->toBeTrue();
});

it('weigert nog steeds een vervalste handtekening', function () {
    [, , $url] = maakHerstelmail();

    $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=' . str_repeat('0', 64), $url))->assertForbidden();
});

it('weigert een verlopen link', function () {
    $order = Order::create(['email' => 'v@example.test', 'status' => 'cancelled', 'total' => 30]);
    $url = URL::temporarySignedRoute('dashed.frontend.recover-order', now()->subMinute(), ['order' => $order->hash]);

    $this->get($url . '&email_id=1')->assertForbidden();
});

it('zet de kortingscode uit de knop-URL in de sessie', function () {
    [, , $url] = maakHerstelmail();

    $this->get($url . '&discount=TERUG-ABCD1234')->assertRedirect();

    expect(session('discountCode'))->toBe('TERUG-ABCD1234');
});

it('negeert een kortingscode die er niet als code uitziet', function () {
    [, , $url] = maakHerstelmail();

    $this->get($url . '&discount=' . urlencode('bad code!'))->assertRedirect();

    expect(session('discountCode'))->toBeNull();
});
