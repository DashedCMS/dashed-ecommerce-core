<?php

use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;
use Dashed\DashedEcommerceCore\Jobs\AbandonedCart\ScheduleAbandonedCartEmailsForCartJob;
use Dashed\DashedEcommerceCore\Listeners\AbandonedCart\QueueAbandonedCartEmailsForOrderListener;

function flowPerTrigger(string $name, array $triggers, bool $active = false): AbandonedCartFlow
{
    $flow = AbandonedCartFlow::create([
        'name' => $name,
        'is_active' => $active,
        'discount_prefix' => 'X',
        'triggers' => $triggers,
    ]);
    AbandonedCartFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1,
        'delay_value' => 1, 'delay_unit' => 'hours',
        'subject' => $name, 'enabled' => true, 'blocks' => [],
    ]);

    return $flow;
}

it('keeps flows with different triggers active at the same time', function () {
    $cart = flowPerTrigger('Wagen', ['cart_with_email']);
    $order = flowPerTrigger('Betaling', ['cancelled_order']);
    $wishlist = flowPerTrigger('Verlanglijst', ['wishlist']);

    $cart->activate();
    $order->activate();
    $wishlist->activate();

    expect($cart->fresh()->is_active)->toBeTrue()
        ->and($order->fresh()->is_active)->toBeTrue()
        ->and($wishlist->fresh()->is_active)->toBeTrue();
});

it('deactivates another active flow that shares a trigger', function () {
    $oud = flowPerTrigger('Oud', ['cart_with_email', 'cancelled_order'], true);
    $ander = flowPerTrigger('Ander', ['wishlist'], true);
    $nieuw = flowPerTrigger('Nieuw', ['cancelled_order']);

    $nieuw->activate();

    expect($nieuw->fresh()->is_active)->toBeTrue()
        ->and($oud->fresh()->is_active)->toBeFalse()
        ->and($ander->fresh()->is_active)->toBeTrue();
});

it('createDefault only deactivates flows for the cart trigger', function () {
    $wagen = flowPerTrigger('Wagen', ['cart_with_email'], true);
    $betaling = flowPerTrigger('Betaling', ['cancelled_order'], true);

    $default = AbandonedCartFlow::createDefault();

    expect($default->is_active)->toBeTrue()
        ->and($default->triggers)->toBe(['cart_with_email'])
        ->and($wagen->fresh()->is_active)->toBeFalse()
        ->and($betaling->fresh()->is_active)->toBeTrue();
});

it('picks the active flow per trigger', function () {
    $wagen = flowPerTrigger('Wagen', ['cart_with_email'], true);
    $betaling = flowPerTrigger('Betaling', ['cancelled_order'], true);
    flowPerTrigger('Uit', ['cart_with_email', 'cancelled_order']);

    expect(AbandonedCartFlow::getActiveFor('cart_with_email')?->id)->toBe($wagen->id)
        ->and(AbandonedCartFlow::getActiveFor('cancelled_order')?->id)->toBe($betaling->id)
        ->and(AbandonedCartFlow::getActiveFor('wishlist'))->toBeNull()
        ->and(AbandonedCartFlow::getActive()?->id)->toBe($wagen->id);
});

it('enrolls carts and cancelled orders in their own flow, even when legacy data has two active flows', function () {
    $wagen = flowPerTrigger('Wagen', ['cart_with_email'], true);
    $betaling = flowPerTrigger('Betaling', ['cancelled_order'], true);
    // Oude data: een tweede actieve wagenflow mag niet nog een reeks inschrijven.
    flowPerTrigger('Tweede wagen', ['cart_with_email'], true);

    $cart = Cart::create(['abandoned_email' => 'k@example.test', 'token' => 'tok-per-trigger']);
    $cart->items()->create(['quantity' => 1, 'unit_price' => 100, 'options_hash' => 'h1']);
    (new ScheduleAbandonedCartEmailsForCartJob($cart->id))->handle();

    $cartRows = AbandonedCartEmail::where('cart_id', $cart->id)->get();
    expect($cartRows)->toHaveCount(1)
        ->and($cartRows->first()->flowStep->flow_id)->toBe($wagen->id);

    $order = Order::create(['email' => 'k@example.test', 'status' => 'cancelled']);
    (new QueueAbandonedCartEmailsForOrderListener())->handle(new OrderCancelledEvent($order));

    $orderRows = AbandonedCartEmail::where('cancelled_order_id', $order->id)->get();
    expect($orderRows)->toHaveCount(1)
        ->and($orderRows->first()->flowStep->flow_id)->toBe($betaling->id);
});
