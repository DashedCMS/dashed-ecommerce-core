<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Models\QuoteLine;

/**
 * De totalen van een offerte: een som per btw-tarief over de regels die
 * meetellen. Bewust niet de winkelwagenmachinerie: een offerte kent geen
 * kortingscodes, cadeaubonnen of betaalkosten, en verzendkosten zijn hier
 * gewoon een regel.
 *
 * Bedragen op een regel staan inclusief btw, dus subtotal en total zijn dat
 * ook, net als op een Order.
 */
class QuoteTotals
{
    public function __construct(
        public readonly float $subtotal,
        public readonly float $vat,
        public readonly float $total,
        public readonly array $vatPerRate,
    ) {
    }

    public static function for(Quote $quote): self
    {
        return self::forLines($quote->lines);
    }

    /** @param iterable<QuoteLine> $lines */
    public static function forLines(iterable $lines): self
    {
        $total = 0.0;
        $vat = 0.0;
        $perRate = [];

        foreach ($lines as $line) {
            if (! $line->counts()) {
                continue;
            }

            $lineTotal = $line->lineTotal();
            $lineVat = $line->lineVat();

            $total += $lineTotal;
            $vat += $lineVat;

            $rate = self::rateKey((float) $line->vat_rate);
            $perRate[$rate] = round(($perRate[$rate] ?? 0.0) + $lineVat, 2);
        }

        $total = round($total, 2);
        $vat = round($vat, 2);

        return new self(
            subtotal: $total,
            vat: $vat,
            total: $total,
            vatPerRate: array_filter($perRate, fn (float $amount) => $amount !== 0.0),
        );
    }

    public function totalExVat(): float
    {
        return round($this->total - $this->vat, 2);
    }

    /** '21' in plaats van '21.00', zodat de sleutel leest als op een order. */
    private static function rateKey(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }
}
