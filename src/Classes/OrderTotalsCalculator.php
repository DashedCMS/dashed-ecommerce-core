<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Herberekent de totalen van een order op basis van zijn eigen orderregels.
 *
 * Conventie gelijk aan de factuur (resources/views/invoices/invoice.blade.php):
 * subtotal is de som van de regeltotalen voor korting, total is subtotal minus
 * de korting. Verzendkosten en betaalkosten zijn gewone orderregels en tellen
 * dus vanzelf mee.
 */
class OrderTotalsCalculator
{
    public static function recalculate(Order $order): void
    {
        $inclusive = (bool) Customsetting::get('taxes_prices_include_taxes');

        $subtotal = 0.0;
        $vatPerRate = [];

        // Bewust via de query en niet via de eager-geladen relatie: die kan
        // verouderd zijn nadat regels net herschreven zijn.
        foreach ($order->orderProducts()->get() as $line) {
            $price = (float) $line->price;
            $rate = (float) ($line->vat_rate ?? 0);

            $subtotal += $price;

            $vat = $inclusive
                ? $price / (100 + $rate) * $rate
                : $price / 100 * $rate;

            $key = (string) (int) round($rate);
            $vatPerRate[$key] = ($vatPerRate[$key] ?? 0.0) + $vat;
        }

        // Een korting kan nooit groter zijn dan het subtotaal. Wordt een order
        // zo aangepast dat er minder overblijft dan de korting, dan zakt de
        // korting mee naar het subtotaal en wordt die verlaagde korting ook
        // opgeslagen. Zonder deze aftopping zou het totaal negatief worden
        // terwijl de btw op nul blijft staan.
        $discount = min((float) ($order->discount ?? 0), $subtotal);

        // De korting drukt de btw proportioneel, ook bij gemengde tarieven.
        $factor = $subtotal > 0 ? ($subtotal - $discount) / $subtotal : 1.0;

        $order->subtotal = round($subtotal, 2);
        $order->discount = round($discount, 2);
        $order->total = round($subtotal - $discount, 2);
        $order->btw = round(array_sum($vatPerRate) * $factor, 2);
        $order->vat_percentages = array_map(
            fn (float $amount): float => round($amount * $factor, 2),
            $vatPerRate
        );
        $order->save();
    }
}
