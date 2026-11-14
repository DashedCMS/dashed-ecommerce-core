<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

/**
 * De btw van een bestaande order, uit zijn eigen regels:
 *
 * - een productregel (en betaalkostenregel) tegen zijn eigen tarief; een
 *   procentuele korting zit al in de regelprijs;
 * - de verzendregel naar verhouding van de omzet per tarief (ShippingVat);
 * - een korting op orderniveau (vast bedrag, promobon, handmatig) haalt 21% btw
 *   over het gebruikte bedrag eraf (onder OSS het standaardtarief van het
 *   land). Een verkochte cadeaubon is een betaalmiddel en doet niets met de btw.
 *
 * Het btw-totaal is de som van de per tarief afgeronde bedragen, zodat factuur
 * en boekhouding nooit een cent verschillen. Een negatief bedrag blijft staan.
 */
class OrderVat
{
    /**
     * @param  float  $orderLevelDiscount  het gebruikte bedrag aan korting op orderniveau (incl. btw)
     * @return array{per_rate: array<string, float>, total: float}
     */
    public static function calculate(Order $order, float $orderLevelDiscount, bool $inclusive): array
    {
        $orderProducts = $order->orderProducts()->get();
        $amountPerRate = ShippingVat::amountPerRateFromOrderProducts($orderProducts);

        $perRate = [];

        foreach ($orderProducts as $line) {
            $price = (float) $line->price;

            if ($line->sku === 'shipping_costs') {
                $split = ShippingVat::split($price, $amountPerRate, $inclusive, OssVat::rateForOrder($order, (float) ShippingVat::FALLBACK_RATE));

                foreach ($split as $key => $vat) {
                    $perRate[$key] = ($perRate[$key] ?? 0.0) + $vat;
                }

                continue;
            }

            $rate = (float) ($line->vat_rate ?? 0);

            if ($rate <= 0) {
                continue;
            }

            $key = OssVat::rateKey($rate);
            $perRate[$key] = ($perRate[$key] ?? 0.0) + ($inclusive ? $price / (100 + $rate) * $rate : $price / 100 * $rate);
        }

        $discountRate = self::orderDiscountRate($order);
        $discountVat = DiscountCode::amountDiscountVat(self::vatBearingDiscount($order, $orderLevelDiscount), $discountRate);

        if ($discountVat > 0) {
            $key = OssVat::rateKey($discountRate);
            $perRate[$key] = ($perRate[$key] ?? 0.0) - $discountVat;
        }

        $perRate = array_map(fn (float $amount): float => round($amount, 2), $perRate);

        return [
            'per_rate' => $perRate,
            'total' => round(array_sum($perRate), 2),
        ];
    }

    /**
     * Het bedrag (incl. btw) aan korting op orderniveau dat de btw verlaagt,
     * afgeleid uit de order zelf. Voor de verzamelfactuur.
     */
    public static function vatBearingOrderDiscount(Order $order): float
    {
        $breakdown = OrderTotalsCalculator::breakdownForLines($order, $order->orderProducts->map(fn ($line) => [
            'price' => (float) $line->price,
            'discount' => (float) ($line->discount ?? 0),
        ])->all());

        return self::vatBearingDiscount($order, $breakdown['net'] - $breakdown['total']);
    }

    /**
     * Het tarief waartegen een korting op orderniveau wordt weggerekend: 21%,
     * of onder OSS het standaardtarief van het land.
     */
    public static function orderDiscountRate(Order $order): float
    {
        return OssVat::rateForOrder($order, (float) DiscountCode::AMOUNT_DISCOUNT_VAT_RATE);
    }

    /**
     * Het deel van de korting op orderniveau dat de btw verlaagt, plus de
     * promobonnen uit de kassa die naast een kortingscode zijn verzilverd.
     */
    protected static function vatBearingDiscount(Order $order, float $orderLevelDiscount): float
    {
        $amount = $order->discountCode?->isSoldGiftcard() ? 0.0 : max(0.0, $orderLevelDiscount);

        // De kassa zet bij alleen cadeaubonnen de eerste bon op discount_code_id
        // en het verzilverde bedrag in discount; die zit dus al in $amount.
        foreach ((array) ($order->applied_gift_cards ?? []) as $redemption) {
            $cardId = (int) ($redemption['discount_code_id'] ?? 0);

            if (! $cardId || $cardId === (int) $order->discount_code_id) {
                continue;
            }

            $card = DiscountCode::find($cardId);

            if ($card && ! $card->isSoldGiftcard()) {
                $amount += (float) ($redemption['redeemed'] ?? 0);
            }
        }

        return $amount;
    }
}
