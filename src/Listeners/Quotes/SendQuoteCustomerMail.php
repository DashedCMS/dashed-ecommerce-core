<?php

namespace Dashed\DashedEcommerceCore\Listeners\Quotes;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Events\Quotes\QuoteExpired;
use Dashed\DashedEcommerceCore\Events\Quotes\QuoteAccepted;
use Dashed\DashedEcommerceCore\Events\Quotes\QuoteRejected;
use Dashed\DashedEcommerceCore\Events\Quotes\QuoteWithdrawn;
use Dashed\DashedEcommerceCore\Mail\Quotes\QuoteExpiredCustomerMail;
use Dashed\DashedEcommerceCore\Mail\Quotes\QuoteAcceptedCustomerMail;
use Dashed\DashedEcommerceCore\Mail\Quotes\QuoteRejectedCustomerMail;
use Dashed\DashedEcommerceCore\Mail\Quotes\QuoteWithdrawnCustomerMail;

/**
 * Eén klantmail per offerte-antwoord, in de taal van de offerte. Binnen
 * rescue(): een mailserver die faalt houdt het antwoord zelf niet tegen.
 */
class SendQuoteCustomerMail
{
    public function handle(QuoteAccepted|QuoteRejected|QuoteWithdrawn|QuoteExpired $event): void
    {
        $quote = $event->quote;

        if (! $quote->email || (property_exists($event, 'notifyCustomer') && ! $event->notifyCustomer)) {
            return;
        }

        $mail = match (true) {
            $event instanceof QuoteAccepted => new QuoteAcceptedCustomerMail($quote),
            $event instanceof QuoteRejected => new QuoteRejectedCustomerMail($quote),
            $event instanceof QuoteWithdrawn => new QuoteWithdrawnCustomerMail($quote),
            $event instanceof QuoteExpired => new QuoteExpiredCustomerMail($quote),
        };

        rescue(function () use ($quote, $mail) {
            $originalLocale = App::getLocale();
            App::setLocale($quote->locale);

            try {
                Mail::to($quote->email)->send($mail);
            } finally {
                App::setLocale($originalLocale);
            }
        });
    }
}
