<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;

class UpdateOrderReservedStock
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCreatedEvent $event): void
    {
        foreach ($event->order->orderProducts as $orderProduct) {
            $product = $orderProduct->product;
            if ($product) {
                $product->calculateReservedStock();
            }
        }
    }
}
