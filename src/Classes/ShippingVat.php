<?php

namespace Dashed\DashedEcommerceCore\Classes;

/**
 * Btw over verzendkosten volgt de btw van de goederen naar verhouding van de
 * omzet per tarief. Kost een bestelling 109 euro aan 9%-producten en 121 euro
 * aan 21%-producten, dan valt 109/230 van de verzendkosten onder 9% en 121/230
 * onder 21%. Producten tegen 0% tellen mee in de verdeling, maar dragen geen
 * btw.
 *
 * Eén plek voor deze verdeling, zodat webshop, kassa, proforma, het wijzigen
 * van een order en creditorders hetzelfde uitkomen. De tarieven die binnenkomen
 * zijn al de tarieven zoals ze op de regels staan (dus ook OSS-tarieven).
 */
class ShippingVat
{
    /**
     * Wanneer er niets is om naar te verdelen (alleen verzendkosten), geldt het
     * algemene tarief.
     */
    public const FALLBACK_RATE = 21;

    /**
     * @param  array<int|string, float>  $amountPerRate  omzet per btw-tarief (incl. 0%) waarnaar verdeeld wordt
     * @return array<string, float> btw per tarief (sleutel OssVat::rateKey()), per tarief afgerond op centen
     */
    public static function split(float $shippingCosts, array $amountPerRate, bool $inclusive, ?float $fallbackRate = null): array
    {
        if (abs($shippingCosts) < 0.005) {
            return [];
        }

        $amountPerRate = array_filter(
            array_map(fn ($amount) => (float) $amount, $amountPerRate),
            fn (float $amount) => $amount > 0
        );

        $total = array_sum($amountPerRate);

        if ($total <= 0) {
            $amountPerRate = [OssVat::rateKey($fallbackRate ?? self::FALLBACK_RATE) => 1.0];
            $total = 1.0;
        }

        $result = [];

        foreach ($amountPerRate as $rate => $amount) {
            $rate = (float) $rate;

            if ($rate <= 0) {
                continue;
            }

            $part = $shippingCosts * ($amount / $total);
            $vat = $inclusive ? $part / (100 + $rate) * $rate : $part / 100 * $rate;

            $key = OssVat::rateKey($rate);
            $result[$key] = ($result[$key] ?? 0.0) + $vat;
        }

        return array_map(fn (float $vat) => round($vat, 2), $result);
    }

    /**
     * Het gemiddelde tarief dat op de verzendregel komt te staan. Alleen ter
     * informatie: de uitsplitsing per tarief komt uit split(), niet hieruit.
     */
    public static function effectiveRate(float $shippingCosts, array $vatPerRate, bool $inclusive): float
    {
        // Eén tarief: precies dat tarief, niet teruggerekend uit afgeronde btw.
        if (count($vatPerRate) === 1) {
            return (float) array_key_first($vatPerRate);
        }

        $vat = array_sum($vatPerRate);
        $net = $inclusive ? $shippingCosts - $vat : $shippingCosts;

        if (abs($net) < 0.005) {
            return 0.0;
        }

        return round($vat / $net * 100, 2);
    }

    /**
     * Omzet per tarief uit de productregels van een order (verzend- en
     * betaalkostenregels tellen niet mee).
     *
     * @param  iterable<\Dashed\DashedEcommerceCore\Models\OrderProduct>  $orderProducts
     * @return array<string, float>
     */
    public static function amountPerRateFromOrderProducts(iterable $orderProducts): array
    {
        $amounts = [];

        foreach ($orderProducts as $orderProduct) {
            if (in_array($orderProduct->sku, ['shipping_costs', 'payment_costs'], true)) {
                continue;
            }

            $key = OssVat::rateKey((float) ($orderProduct->vat_rate ?? 0));
            $amounts[$key] = ($amounts[$key] ?? 0.0) + (float) $orderProduct->price;
        }

        return $amounts;
    }
}
