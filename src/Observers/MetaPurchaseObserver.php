<?php

namespace Dashed\DashedEcommerceCore\Observers;

use Throwable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Jobs\SendMetaPurchaseEventJob;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;

/**
 * Vangt elke overgang naar `paid` af: webhook, terugkeer-URL, handmatige
 * betaling en de late bevestiging van een bankoverschrijving. Dat laatste
 * mist OrderMarkedAsPaidEvent, dat bovendien al bij "wacht op bevestiging"
 * vuurt. Of er echt een event gaat, beslist de job.
 */
class MetaPurchaseObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status') || $order->status !== 'paid') {
            return;
        }

        try {
            if (! MetaCapiSettings::for($order->site_id)->enabled()) {
                return;
            }

            SendMetaPurchaseEventJob::dispatch($order->id)->onQueue('ecommerce')->afterCommit();
        } catch (Throwable $e) {
            // Tracking mag een statuswijziging nooit tegenhouden.
            report($e);
        }
    }
}
