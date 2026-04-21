<?php

namespace Dashed\DashedEcommerceCore\Listeners\AbandonedCart;

use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

class CancelPendingAbandonedEmailsListener
{
    public function handle(OrderMarkedAsPaidEvent $event): void
    {
        $email = $event->order->email;

        if (blank($email)) {
            return;
        }

        AbandonedCartEmail::cancelPendingForEmail($email, 'converted');
    }
}
