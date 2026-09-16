<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\OrderPayment;

/**
 * Een PSP meldt (via de exchange-webhook of de klantpagina na betaling) dat
 * er op deze betaling is terugbetaald. De betaling zelf blijft 'paid': een
 * terugbetaling hoort op de creditorder van een retour, niet op de
 * oorspronkelijke betaling. Wie hierop luistert (dashed-ecommerce-paynl)
 * zoekt die creditorder op.
 */
class PaymentRefundReportedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public OrderPayment $payment)
    {
    }
}
