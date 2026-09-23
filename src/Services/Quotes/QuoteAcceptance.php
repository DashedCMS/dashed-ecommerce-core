<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use RuntimeException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Quote;

/**
 * Akkoord en afwijzen. De statusovergang zelf loopt in een transactie met een
 * lock en een vlag die alleen de aanroep die de overgang echt heeft gedaan
 * laat doorlopen: twee bijna-gelijktijdige verzoeken zien allebei de
 * vergrendelde rij, maar precies een van de twee vindt hem nog op
 * "verstuurd" staan. De andere krijgt de al-bijgewerkte rij terug en stopt
 * meteen, ook al is de order op dat moment nog niet gebouwd (dat gebeurt
 * bewust buiten de transactie, zie hieronder). Een unieke index op
 * dashed__orders.quote_id is de databasebackstop voor het resterende venster
 * tussen committen en het wegschrijven van order_id.
 */
class QuoteAcceptance
{
    /**
     * @param  array<int, int>  $selectedLineIds
     *
     * Een offerte die al geaccepteerd is maar nog geen order heeft (een
     * eerdere poging is na de statusovergang vastgelopen op de PDF of de
     * order) telt hier niet als "klaar": deze aanroep slaat de overgang dan
     * over en bouwt gewoon de order alsnog. Alleen een geaccepteerde offerte
     * met een order erop is echt af.
     */
    public static function accept(Quote $quote, array $selectedLineIds, string $name, string $ip): Quote
    {
        if ($quote->status === Quote::STATUS_ACCEPTED && $quote->order_id) {
            return $quote;
        }

        if ($quote->status !== Quote::STATUS_ACCEPTED) {
            if (! $quote->isAnswerable()) {
                throw new RuntimeException(__('Deze offerte kan niet meer geaccepteerd worden'));
            }

            App::setLocale($quote->locale);

            $didAccept = false;

            $quote = DB::transaction(function () use ($quote, $selectedLineIds, $name, $ip, &$didAccept) {
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
                $locked->total = QuoteTotals::for($locked)->total;
                $locked->save();

                $didAccept = true;

                return $locked;
            });

            // Deze aanroep heeft de overgang niet zelf gedaan: een ander
            // verzoek won de lock. Die is dan zelf verantwoordelijk voor de
            // order; hier is niets meer te doen.
            if (! $didAccept) {
                return $quote;
            }
        }

        // Vanaf hier heeft deze aanroep de offerte zelf net geaccepteerd, of
        // trof haar al geaccepteerd aan zonder order (de herkansing
        // hierboven). Beide gevallen bouwen de order alsnog.
        if ($quote->order_id) {
            return $quote;
        }

        QuotePdf::store($quote, accepted: true);

        $order = QuoteToOrder::build($quote);
        $quote->order_id = $order->id;
        $quote->save();

        QuoteAdminNotifier::answered($quote);

        return $quote;
    }

    public static function reject(Quote $quote, string $reason): Quote
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

        QuoteAdminNotifier::answered($quote);

        return $quote;
    }
}
