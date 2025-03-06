<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Jobs\PrintDocumentsAfterPaidOrderJob;

class PrintDocumentsAfterPaidOrder
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
    public function handle(OrderMarkedAsPaidEvent $event): void
    {
        PrintDocumentsAfterPaidOrderJob::dispatch($event->order);
    }
}
