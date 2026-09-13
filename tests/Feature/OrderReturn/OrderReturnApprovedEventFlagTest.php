<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent;

it('geeft notifyCustomer true door bij approve()', function () {
    Mail::fake();
    Event::fake([OrderReturnApprovedEvent::class]);
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $return->approve();

    Event::assertDispatched(OrderReturnApprovedEvent::class, fn ($e) => $e->orderReturn->is($return) && $e->notifyCustomer === true);
});

it('kan met notifyCustomer false worden gemaakt', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $event = new OrderReturnApprovedEvent($return, false);

    expect($event->notifyCustomer)->toBeFalse();
});
