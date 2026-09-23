<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use RuntimeException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Mail\QuoteMail;
use Dashed\DashedEcommerceCore\Mail\QuoteReminderMail;

/**
 * Een offerte versturen: nummer toekennen, totaal vastleggen, PDF maken,
 * mailen, stempelen. De enige plek die dat doet, zodat het scherm, een
 * revisie en een eventuele latere API dezelfde weg lopen.
 */
class QuoteSender
{
    public static function send(Quote $quote, string $email, ?string $cc = null, ?string $message = null): void
    {
        if ($quote->lines()->count() === 0) {
            throw new RuntimeException(__('Een offerte zonder regels kan niet verstuurd worden'));
        }

        $quote->email = $email;
        $quote->total = QuoteTotals::for($quote)->total;
        $quote->save();

        QuoteNumber::assign($quote);

        // De mail en de PDF staan in de taal van de offerte, niet in die van de
        // beheerder die op de knop drukt.
        App::setLocale($quote->locale);

        QuotePdf::store($quote->fresh());

        $mail = Mail::to($email);
        if ($cc) {
            $mail->cc($cc);
        }
        $mail->send(new QuoteMail($quote->fresh(), $message));

        $quote->status = Quote::STATUS_SENT;
        $quote->sent_at = now();
        $quote->reminder_sent_at = null;
        $quote->save();
    }

    public static function sendReminder(Quote $quote): void
    {
        if ($quote->reminder_sent_at || $quote->status !== Quote::STATUS_SENT) {
            return;
        }

        App::setLocale($quote->locale);

        Mail::to($quote->email)->send(new QuoteReminderMail($quote));

        $quote->reminder_sent_at = now();
        $quote->save();
    }
}
