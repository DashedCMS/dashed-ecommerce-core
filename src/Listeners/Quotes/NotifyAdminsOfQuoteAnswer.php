<?php

namespace Dashed\DashedEcommerceCore\Listeners\Quotes;

use Dashed\DashedEcommerceCore\Events\Quotes\QuoteAccepted;
use Dashed\DashedEcommerceCore\Events\Quotes\QuoteRejected;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteAdminNotifier;

/** Mail en belletje voor de beheerders; de app-push doet dashed-mobile-api. */
class NotifyAdminsOfQuoteAnswer
{
    public function handle(QuoteAccepted|QuoteRejected $event): void
    {
        QuoteAdminNotifier::answered($event->quote);
    }
}
