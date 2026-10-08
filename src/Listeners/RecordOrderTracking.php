<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Throwable;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Services\Meta\OrderTrackingRecorder;

/**
 * OrderCreatedEvent vuurt alleen vanuit de webshop-checkout, binnen het
 * verzoek van de klant. De rij die hier ontstaat is daarmee ook het kenmerk
 * "dit is een webshop-order" voor de Conversions API.
 */
class RecordOrderTracking
{
    public function handle(OrderCreatedEvent $event): void
    {
        try {
            app(OrderTrackingRecorder::class)->record($event->order, request());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
