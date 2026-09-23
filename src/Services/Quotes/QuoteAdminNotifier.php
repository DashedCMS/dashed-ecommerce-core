<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Notification;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Mail\QuoteAnsweredMail;
use Dashed\DashedEcommerceCore\Notifications\QuoteAnsweredNotification;

/**
 * Een antwoord van de klant melden: een belletje in het CMS en een mail naar
 * de ontvangers die al voor orders zijn ingesteld.
 *
 * De database-melding gaat uitdrukkelijk alleen naar rol admin of superadmin.
 * `role` is een kolom en geen scope; zonder die filter krijgt elke
 * webshopklant de melding in zijn notifications-rij, en dat is bij lovora al
 * een keer uit de hand gelopen.
 */
class QuoteAdminNotifier
{
    public static function answered(Quote $quote): void
    {
        rescue(function () use ($quote) {
            $admins = User::query()->whereIn('role', ['admin', 'superadmin'])->get();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new QuoteAnsweredNotification($quote));
            }

            foreach (Mails::getAdminNotificationEmails() as $email) {
                AdminNotifier::send(new QuoteAnsweredMail($quote), $email);
            }
        });
    }
}
