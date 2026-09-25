<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use RuntimeException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Events\Quotes\QuoteAccepted;
use Dashed\DashedEcommerceCore\Events\Quotes\QuoteRejected;

/**
 * Akkoord en afwijzen. De statusovergang zelf loopt in een transactie met een
 * lock en een vlag die alleen de aanroep die de overgang echt heeft gedaan
 * laat doorlopen: twee bijna-gelijktijdige verzoeken zien allebei de
 * vergrendelde rij, maar precies een van de twee vindt hem nog op
 * "verstuurd" staan. De andere krijgt de al-bijgewerkte rij terug en stopt
 * meteen. Een akkoord maakt geen order; dat doet QuoteConverter, op verzoek
 * van de klant of de beheerder.
 */
class QuoteAcceptance
{
    /**
     * @param  array<int, int>  $selectedLineIds
     *
     * De handtekening is een PNG-data-URL uit het tekenvlak; wat
     * QuoteSignature niet als echte PNG herkent wordt niet opgeslagen.
     */
    public static function accept(Quote $quote, array $selectedLineIds, string $name, string $ip, ?string $signature = null, bool $notifyCustomer = true): Quote
    {
        if ($quote->status === Quote::STATUS_ACCEPTED) {
            return $quote;
        }

        if (! $quote->isAnswerable()) {
            throw new RuntimeException(__('Deze offerte kan niet meer geaccepteerd worden'));
        }

        App::setLocale($quote->locale);

        $didAccept = false;

        $quote = DB::transaction(function () use ($quote, $selectedLineIds, $name, $ip, $signature, &$didAccept) {
            /** @var Quote $locked */
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->first();

            if ($locked->status !== Quote::STATUS_SENT) {
                return $locked;
            }

            // Wat de klant koos vastleggen op de regels zelf: de akkoord-PDF
            // en de order lezen daarna gewoon de regels, zonder losse lijst.
            foreach ($locked->lines as $line) {
                if (! $line->is_optional) {
                    continue;
                }
                $line->is_selected = in_array($line->id, $selectedLineIds, true);
                $line->save();
            }

            $locked->load('lines');

            $locked->status = Quote::STATUS_ACCEPTED;
            $locked->accepted_at = now();
            $locked->accepted_name = $name;
            $locked->accepted_ip = $ip;
            $locked->accepted_signature = QuoteSignature::normalize($signature);
            $locked->total = QuoteTotals::for($locked)->total;
            $locked->save();

            $didAccept = true;

            return $locked;
        });

        // Deze aanroep heeft de overgang niet zelf gedaan: een ander
        // verzoek won de lock. Die is dan zelf verantwoordelijk voor de
        // melding; hier is niets meer te doen.
        if (! $didAccept) {
            return $quote;
        }

        QuotePdf::store($quote, accepted: true);

        QuoteAccepted::dispatch($quote, $notifyCustomer);

        return $quote;
    }

    public static function reject(Quote $quote, string $reason, bool $notifyCustomer = true): Quote
    {
        if ($quote->status === Quote::STATUS_REJECTED) {
            return $quote;
        }

        if (! $quote->isAnswerable()) {
            throw new RuntimeException(__('Deze offerte kan niet meer afgewezen worden'));
        }

        $didReject = false;

        $quote = DB::transaction(function () use ($quote, $reason, &$didReject) {
            /** @var Quote $locked */
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->first();

            if ($locked->status !== Quote::STATUS_SENT) {
                return $locked;
            }

            $locked->status = Quote::STATUS_REJECTED;
            $locked->rejected_at = now();
            $locked->rejection_reason = $reason;
            $locked->save();

            $didReject = true;

            return $locked;
        });

        if (! $didReject) {
            return $quote;
        }

        QuoteRejected::dispatch($quote, $notifyCustomer);

        return $quote;
    }
}
