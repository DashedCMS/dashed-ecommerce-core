<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Quote;

/**
 * Nummer toekennen bij het eerste versturen, zoals een factuurnummer. Een
 * revisie erft het nummer van de eerste versie: het is dezelfde offerte, en
 * de versie staat er los naast.
 *
 * Anders dan Order::generateInvoiceId() loopt het ophogen hier onder een lock.
 * Twee beheerders die tegelijk versturen kregen daar hetzelfde nummer.
 */
class QuoteNumber
{
    public static function assign(Quote $quote): string
    {
        if ($quote->quote_number) {
            return $quote->quote_number;
        }

        $root = $quote->rootQuote();
        if ($root->id !== $quote->id && $root->quote_number) {
            $quote->quote_number = $root->quote_number;
            $quote->save();

            return $quote->quote_number;
        }

        $siteId = $quote->site_id ?: Sites::getActive();
        $number = Cache::lock('quote-number:'.$siteId, 10)->block(5, function () use ($siteId) {
            $next = ((int) Customsetting::get('current_quote_number', $siteId, 1000)) + 1;
            Customsetting::set('current_quote_number', $next, $siteId);

            return $next;
        });

        $quote->quote_number = self::format(QuoteDefaults::numberFormat($siteId), $number);
        $quote->save();

        return $quote->quote_number;
    }

    public static function format(string $format, int $number): string
    {
        return strtr($format, [
            ':jaar:' => (string) now()->year,
            ':maand:' => now()->format('m'),
            ':nummer:' => (string) $number,
        ]);
    }
}
