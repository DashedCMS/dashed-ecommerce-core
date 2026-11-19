<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\ProductReviews;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\ProductReviewRequest;

/**
 * Plant het reviewverzoek in zodra een bestelling op Afgehandeld gaat. Dat
 * is de status die ook de MyParcel- en Veloyd-commando's zetten als alles
 * bezorgd is, dus "afgeleverd" en "met de hand afgehandeld" komen hier
 * allebei langs. De mail zelf gaat pas na de ingestelde wachttijd, via
 * dashed:send-product-review-requests, die alle voorwaarden nog een keer
 * toetst.
 */
class ReviewRequestScheduler
{
    public static function schedule(Order $order): ?ProductReviewRequest
    {
        if (! self::eligible($order)) {
            return null;
        }

        $siteId = (string) $order->site_id;
        $existing = ProductReviewRequest::query()->where('order_id', $order->getKey())->first();

        if ($existing) {
            // Heropend en opnieuw afgehandeld zonder dat de mail al weg was:
            // de wachttijd telt vanaf de laatste levering.
            if (! $existing->sent_at && ! $existing->skipped_reason) {
                $existing->forceFill(['due_at' => now()->addDays(ProductReviewSettings::delayDays($siteId))])->save();
            }

            return $existing;
        }

        return rescue(fn () => ProductReviewRequest::create([
            'order_id' => $order->getKey(),
            'site_id' => $siteId,
            'due_at' => now()->addDays(ProductReviewSettings::delayDays($siteId)),
        ]), null, false);
    }

    /**
     * Bol-klanten zijn klant van Bol en krijgen nooit iets van ons; concepten,
     * proforma's, creditorders en onbetaalde of geannuleerde bestellingen
     * horen geen reviewverzoek te krijgen.
     */
    public static function eligible(Order $order): bool
    {
        if (! ProductReviewSettings::requestsEnabled((string) $order->site_id)) {
            return false;
        }

        if (blank($order->email) || (string) $order->order_origin === 'Bol') {
            return false;
        }

        if (in_array((string) $order->invoice_id, ['PROFORMA', 'RETURN'], true)) {
            return false;
        }

        if (in_array((string) $order->status, ['concept', 'cancelled', 'return'], true)) {
            return false;
        }

        return $order->isPaidFor();
    }
}
