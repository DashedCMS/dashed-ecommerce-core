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

        // Quote::isExpired() is voor een concept altijd false, dus zonder deze
        // controle blijft de verstuurknop staan en komt de offerte aan terwijl de
        // klant er al niets meer mee kan: de publieke pagina toont hem dan
        // meteen het verlopen-scherm. Hier en niet in de knop, want dit is de
        // enige deur waar het scherm, een revisie en een latere API door gaan.
        if ($quote->valid_until !== null && $quote->valid_until->endOfDay()->isPast()) {
            throw new RuntimeException(__('De geldigheidsdatum ligt in het verleden; zet "Geldig tot en met" op een datum in de toekomst'));
        }

        $quote->email = $email;
        $quote->total = QuoteTotals::for($quote)->total;
        $quote->save();

        QuoteNumber::assign($quote);

        // De mail en de PDF staan in de taal van de offerte, niet in die van de
        // beheerder die op de knop drukt. Zonder terugzetten blijft de rest van
        // dit verzoek (of, op een queue-worker, de volgende job) in die taal
        // hangen, dus dat gebeurt in een finally, ook als het versturen faalt.
        $originalLocale = App::getLocale();
        App::setLocale($quote->locale);

        try {
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
        } finally {
            App::setLocale($originalLocale);
        }
    }

    public static function sendReminder(Quote $quote): bool
    {
        if ($quote->reminder_sent_at || $quote->status !== Quote::STATUS_SENT) {
            return false;
        }

        $originalLocale = App::getLocale();
        App::setLocale($quote->locale);

        try {
            Mail::to($quote->email)->send(new QuoteReminderMail($quote));

            $quote->reminder_sent_at = now();
            $quote->save();

            return true;
        } finally {
            App::setLocale($originalLocale);
        }
    }
}
