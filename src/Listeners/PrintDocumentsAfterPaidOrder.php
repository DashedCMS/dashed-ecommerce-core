<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedEcommerceCore\Events\Products\ProductSavedEvent;

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
        $order = $event->order;
        $order->printInvoice();
        $order->printPackingSlip();
    }
}
