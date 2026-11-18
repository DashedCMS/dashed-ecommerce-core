<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\ReminderPlanning;

/** Verstuurt de herinneringsmail "Nog iets vergeten?" voor één order; controleert op het moment zelf opnieuw of het nog mag. */
class SendOrderAddOnReminderJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** Een uur uniek per order: plan() draait elke vijf minuten en mag geen tweede job voor dezelfde order zetten. */
    public int $uniqueFor = 3600;

    public function __construct(public int $orderId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function handle(ReminderPlanning $planning): void
    {
        $planning->send($this->orderId);
    }
}
