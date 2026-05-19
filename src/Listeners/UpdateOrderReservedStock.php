<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;

class UpdateOrderReservedStock implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

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
