<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Support\Facades\Event;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;

it('dispatches OrderCancelledEvent when order is cancelled', function () {
    Event::fake([OrderCancelledEvent::class]);

    $order = Order::create([
        'status' => 'pending',
        'email' => 't@example.test',
    ]);

    $order->markAsCancelled();

    Event::assertDispatched(
        OrderCancelledEvent::class,
        fn (OrderCancelledEvent $event) => $event->order->is($order),
    );
});
