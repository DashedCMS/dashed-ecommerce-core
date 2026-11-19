<?php

namespace Dashed\DashedEcommerceCore\Listeners\ProductReviews;

use Dashed\DashedEcommerceCore\Services\ProductReviews\ReviewRequestScheduler;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

/**
 * Plant het reviewverzoek in zodra een bestelling op Afgehandeld gaat. Niet
 * via de wachtrij: het is één insert, en zo staat de rij er ook als er geen
 * worker draait.
 */
class ScheduleProductReviewRequestListener
{
    public function handle(OrderFulfillmentStatusChangedEvent $event): void
    {
        if ($event->newStatus !== 'handled') {
            return;
        }

        rescue(fn () => ReviewRequestScheduler::schedule($event->order), report: true);
    }
}
