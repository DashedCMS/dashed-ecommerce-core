<?php

namespace Dashed\DashedEcommerceCore\Listeners\Referrals;

use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Services\Referrals\ReferralProgram;

/**
 * Legt een verwijzing vast zodra een bestelling met een verwijscode betaald
 * is. Een fout hier mag het betalen nooit tegenhouden.
 */
class RecordReferralListener
{
    public function handle(OrderMarkedAsPaidEvent $event): void
    {
        rescue(fn () => ReferralProgram::recordPaidOrder($event->order));
    }
}
