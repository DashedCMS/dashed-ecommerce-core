<?php

namespace Dashed\DashedEcommerceCore\Observers;

use Throwable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Services\Giftcards\GiftcardIssuer;

/**
 * Vangt elke overgang naar `paid` af (ook bankoverschrijving en handmatige betaling).
 */
class GiftcardIssueObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status') || $order->status !== 'paid') {
            return;
        }

        try {
            app(GiftcardIssuer::class)->issueFor($order);
        } catch (Throwable $e) {
            // Uitgifte mag de statuswijziging nooit tegenhouden; de volgende trigger vult aan.
            report($e);
        }
    }
}
