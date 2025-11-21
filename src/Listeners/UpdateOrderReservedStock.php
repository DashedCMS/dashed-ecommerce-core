<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedEcommerceCore\Events\Products\ProductSavedEvent;

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
