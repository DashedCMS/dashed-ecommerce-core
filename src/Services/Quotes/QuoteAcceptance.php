<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use RuntimeException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Quote;

/**
 * Akkoord en afwijzen. Alles in een transactie met een statuscontrole erin,
 * zodat twee keer klikken geen tweede order oplevert: het tweede verzoek ziet
 * de offerte niet meer op "verstuurd" staan en doet niets.
 */
class QuoteAcceptance
{
    /** @param array<int, int> $selectedLineIds */
    public static function accept(Quote $quote, array $selectedLineIds, string $name, string $ip): Quote
    {
        if ($quote->status === Quote::STATUS_ACCEPTED) {
            return $quote;
        }

        if (! $quote->isAnswerable()) {
            throw new RuntimeException(__('Deze offerte kan niet meer geaccepteerd worden'));
        }

        App::setLocale($quote->locale);

        $quote = DB::transaction(function () use ($quote, $selectedLineIds, $name, $ip) {
            /** @var Quote $locked */
            $locked = Quote::query()->whereKey($quote->id)->lockForUpdate()->first();

            if ($locked->status !== Quote::STATUS_SENT) {
                return $locked;
            }

            // Wat de klant koos vastleggen op de regels zelf: de akkoord-PDF en
            // de order lezen daarna gewoon de regels, zonder losse lijst.
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

            return $locked;
        });

        if ($quote->status !== Quote::STATUS_ACCEPTED || $quote->order_id) {
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

        $quote->status = Quote::STATUS_REJECTED;
        $quote->rejected_at = now();
        $quote->rejection_reason = $reason;
        $quote->save();

        QuoteAdminNotifier::answered($quote);

        return $quote;
    }
}
