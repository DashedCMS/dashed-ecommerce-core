<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;

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
