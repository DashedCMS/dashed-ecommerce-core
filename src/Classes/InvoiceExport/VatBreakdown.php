<?php

namespace Dashed\DashedEcommerceCore\Classes\InvoiceExport;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Dashed\DashedEcommerceCore\Classes\OrderVat;
use Dashed\DashedEcommerceCore\Classes\ShippingVat;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;

/**
 * De btw-uitsplitsing van de verzamelfactuur.
 *
 * Alles wordt afgeleid uit de inclusieve bedragen per tarief:
 * btw = round(incl * tarief / (100 + tarief), 2), ex = incl - btw. Zo komt elke
 * rij exact op het tarief uit en blijft de inclusieve kolom gelijk aan de
 * ontvangen omzet. Volgorde per order: ICP, dan OSS, dan de normale zone. Een order zonder
 * bruikbare tarieven (geen regels, geen btw) valt altijd in de normale zone.
 *
 * Verkochte cadeaubonnen (regels van een product met isGiftcardProduct()) zijn
 * een meervoudige voucher: geen omzet in een btw-tarief maar een aparte post
 * `soldGiftcards`. Met een bon betaalde orders (OrderPayment psp 'giftcard')
 * houden hun volle omzet en btw; `redeemedGiftcards` is alleen ter informatie.
 */
class VatBreakdown
{
    public static function calculate(iterable $orders): array
    {
        $total = 0.0;
        $discount = 0.0;
        $homeInclPerRate = [];
        $normalZones = [];
        $ossRows = [];
        $icpTotals = [];
        $soldGiftcards = 0.0;
        $redeemedGiftcards = 0.0;

        foreach ($orders as $order) {
            // De kassa trekt een verzilverde verkochte bon van het ordertotaal af
            // in plaats van hem als betaling te boeken (en zet hem bij alleen
            // bonnen in discount). Het is een betaling, dus omzet en geen korting.
            // Alleen voor orders met de huidige rekenregels, zodat een al
            // aangegeven periode niet verandert.
            $posSold = $order->usesCurrentVatCalculation() ? static::posSoldGiftcardRedemptions($order) : 0.0;

            $discount += (float) $order->discount - ($posSold > 0 && $order->discountCode?->isSoldGiftcard() ? (float) $order->discount : 0.0);
            $total += (float) $order->total;

            $soldOnOrder = static::giftcardSales($order);
            $soldGiftcards += $soldOnOrder;
            $redeemedGiftcards += static::giftcardRedemptions($order) + $posSold;
            $revenueIncl = (float) $order->total - $soldOnOrder + $posSold;

            $shippingZone = $order->shippingMethod?->shippingZone;
            if (! $shippingZone) {
                $shippingZone = ShoppingCart::getShippingZoneByCountry($order->invoice_country ?: $order->country);
            }
            $country = $order->invoice_country ?: $order->country ?: 'Onbekend';
            $zoneName = ($shippingZone->name ?? null) ?: 'Onbekende zone';

            // ICP / verlegd: zone heeft reverse charge aan én klant heeft btw-nummer.
            if ((bool) ($shippingZone->vat_reverse_charge ?? false) && ! empty($order->btw_id)) {
                $icpKey = $country . '|' . $order->btw_id;

                $icpTotals[$icpKey] ??= [
                    'country' => $country,
                    'vat_number' => $order->btw_id,
                    'revenue' => 0,
                    'zone' => $zoneName,
                ];
                $icpTotals[$icpKey]['revenue'] += ($revenueIncl - (float) $order->btw);

                continue;
            }

            $inclPerRate = static::inclPerRate($order);
            $ossCountry = OssVat::destinationFor($order);

            if ($ossCountry && count($inclPerRate) > 0) {
                foreach ($inclPerRate as $rate => $incl) {
                    $ossRate = OssVat::rateFor($ossCountry, (float) $rate, $order->site_id);
                    $rowKey = $ossCountry . '|' . OssVat::rateKey($ossRate);

                    $ossRows[$rowKey] ??= [
                        'country_code' => $ossCountry,
                        'country' => OssVat::countryName($ossCountry),
                        'rate' => $ossRate,
                        'incl_vat' => 0.0,
                    ];
                    $ossRows[$rowKey]['incl_vat'] += $incl;
                }

                continue;
            }

            // Een order met alleen cadeaubonnen heeft geen omzet in een zone.
            if (abs($soldOnOrder) >= 0.005 && abs($revenueIncl) < 0.005) {
                continue;
            }

            $normalZones[$zoneName] ??= ['zone' => $zoneName, 'incl_vat' => 0.0, 'rates' => []];
            $normalZones[$zoneName]['incl_vat'] += $revenueIncl;

            foreach ($inclPerRate as $rate => $incl) {
                $normalZones[$zoneName]['rates'][$rate] = ($normalZones[$zoneName]['rates'][$rate] ?? 0.0) + $incl;
                $homeInclPerRate[$rate] = ($homeInclPerRate[$rate] ?? 0.0) + $incl;
            }
        }

        $vatPercentages = [];
        foreach ($homeInclPerRate as $rate => $incl) {
            $vatPercentages[(string) $rate] = static::vatFromIncl((float) $incl, (float) $rate);
        }

        $normalZoneTotals = collect($normalZones)
            ->map(function (array $zone) {
                $vat = 0.0;
                foreach ($zone['rates'] as $rate => $incl) {
                    $vat += static::vatFromIncl((float) $incl, (float) $rate);
                }

                return [
                    'zone' => $zone['zone'],
                    'incl_vat' => round($zone['incl_vat'], 2),
                    'vat' => round($vat, 2),
                    'ex_vat' => round($zone['incl_vat'] - $vat, 2),
                ];
            })
            ->sortBy('zone')
            ->values()
            ->all();

        $ossTotals = collect($ossRows)
            ->map(function (array $row) {
                $incl = round($row['incl_vat'], 2);
                $vat = static::vatFromIncl($incl, (float) $row['rate']);

                return array_merge($row, [
                    'incl_vat' => $incl,
                    'vat' => $vat,
                    'ex_vat' => round($incl - $vat, 2),
                ]);
            })
            ->filter(fn (array $row) => abs($row['incl_vat']) >= 0.005)
            ->sortBy([['country', 'asc'], ['rate', 'asc']])
            ->values()
            ->all();

        $ossTotal = [
            'incl_vat' => round(array_sum(array_column($ossTotals, 'incl_vat')), 2),
            'vat' => round(array_sum(array_column($ossTotals, 'vat')), 2),
            'ex_vat' => round(array_sum(array_column($ossTotals, 'ex_vat')), 2),
        ];

        $foreignVat = $ossTotal['vat'];
        $btw = round(array_sum($vatPercentages) + $foreignVat, 2);
        $total = round($total, 2);
        $soldGiftcards = round($soldGiftcards, 2);

        return [
            'total' => $total,
            'discount' => round($discount, 2),
            'btw' => $btw,
            'subTotal' => round($total - $btw - $soldGiftcards, 2),
            'soldGiftcards' => $soldGiftcards,
            'redeemedGiftcards' => round($redeemedGiftcards, 2),
            'vatPercentages' => $vatPercentages,
            'foreignVat' => $foreignVat,
            'normalZoneTotals' => $normalZoneTotals,
            'ossTotals' => $ossTotals,
            'ossTotal' => $ossTotal,
            'icpTotals' => collect($icpTotals)->sortBy([
                ['country', 'asc'],
                ['vat_number', 'asc'],
            ])->values()->all(),
        ];
    }

    /**
     * Som van de regels van verkochte cadeaubonnen op de order (incl, btw 0%).
     */
    public static function giftcardSales(Order $order): float
    {
        $sum = 0.0;

        foreach ($order->orderProducts as $orderProduct) {
            if (static::isGiftcardLine($orderProduct)) {
                $sum += (float) $orderProduct->price;
            }
        }

        return $sum;
    }

    /**
     * Som van de betaalde cadeaubon-betalingen (psp 'giftcard') op de order.
     */
    public static function giftcardRedemptions(Order $order): float
    {
        return (float) $order->orderPayments
            ->where('psp', 'giftcard')
            ->where('status', 'paid')
            ->sum('amount');
    }

    /**
     * Wat er in de kassa met verkochte cadeaubonnen is betaald. De kassa boekt
     * dat niet als betaling maar trekt het van order->total af (applied_gift_cards);
     * een verkochte bon is een meervoudige voucher, dus het blijft gewoon omzet.
     */
    public static function posSoldGiftcardRedemptions(Order $order): float
    {
        $sum = 0.0;

        foreach ((array) ($order->applied_gift_cards ?? []) as $redemption) {
            $redeemed = (float) ($redemption['redeemed'] ?? 0);

            if ($redeemed <= 0) {
                continue;
            }

            $card = DiscountCode::find((int) ($redemption['discount_code_id'] ?? 0));

            if ($card && $card->isSoldGiftcard()) {
                $sum += $redeemed;
            }
        }

        return $sum;
    }

    protected static function isGiftcardLine($orderProduct): bool
    {
        return (bool) $orderProduct->product?->isGiftcardProduct();
    }

    public static function vatFromIncl(float $incl, float $rate): float
    {
        return round($incl * $rate / (100 + $rate), 2);
    }

    /**
     * Inclusieve omzet per btw-tarief op basis van de orderregels (elke regel
     * heeft een eigen vat_rate). Dezelfde regels als OrderVat:
     *
     * - de verzendregel wordt naar verhouding van de productomzet per tarief
     *   verdeeld (zijn eigen vat_rate is maar een gemiddelde);
     * - een korting op orderniveau (vast bedrag, promobon, handmatig) gaat
     *   volledig van het 21%-tarief af (onder OSS het landtarief), niet
     *   evenredig over alle tarieven.
     *
     * Wat er dan nog verschilt met order->total (afronding, of in de kassa een
     * verzilverde verkochte bon) wordt geschaald. Regels van verkochte
     * cadeaubonnen tellen niet mee; zij gaan van het ordertotaal af.
     *
     * Een order van vóór deze regels (Order::usesCurrentVatCalculation()) houdt
     * de oude verdeling: verzendregel tegen zijn eigen tarief en een korting
     * evenredig geschaald, passend bij de btw die toen op de order is gezet.
     *
     * @return array<string|int, float> tariefsleutel (OssVat::rateKey) → incl
     */
    public static function inclPerRate(Order $order): array
    {
        $perRate = [];
        $sumLines = 0.0;
        $shipping = 0.0;
        // Orders van vóór de huidige rekenregels houden de oude verdeling, zodat
        // een al aangegeven periode bij opnieuw exporteren niet verandert.
        $currentRules = $order->usesCurrentVatCalculation();

        $giftcardSales = 0.0;

        foreach ($order->orderProducts as $orderProduct) {
            if (static::isGiftcardLine($orderProduct)) {
                $giftcardSales += (float) $orderProduct->price;

                continue;
            }

            if ($currentRules && $orderProduct->sku === 'shipping_costs') {
                $shipping += (float) $orderProduct->price;
                $sumLines += (float) $orderProduct->price;

                continue;
            }

            $rate = OssVat::rateKey((float) ($orderProduct->vat_rate ?? 21));
            $perRate[$rate] = ($perRate[$rate] ?? 0.0) + (float) $orderProduct->price;
            $sumLines += (float) $orderProduct->price;
        }

        if (abs($shipping) >= 0.005) {
            $base = array_filter($perRate, fn ($amount) => $amount > 0);
            $baseTotal = array_sum($base);

            if ($baseTotal <= 0) {
                $base = [OssVat::rateKey(OssVat::rateForOrder($order, (float) ShippingVat::FALLBACK_RATE)) => 1.0];
                $baseTotal = 1.0;
            }

            foreach ($base as $rate => $amount) {
                $perRate[$rate] = ($perRate[$rate] ?? 0.0) + $shipping * ($amount / $baseTotal);
            }
        }

        $orderDiscount = $currentRules ? OrderVat::vatBearingOrderDiscount($order) : 0.0;

        if ($orderDiscount >= 0.005) {
            $discountRate = OssVat::rateKey(OrderVat::orderDiscountRate($order));
            $perRate[$discountRate] = ($perRate[$discountRate] ?? 0.0) - $orderDiscount;
            $sumLines -= $orderDiscount;
        }

        $totalIncl = (float) $order->total - $giftcardSales
            + ($currentRules ? static::posSoldGiftcardRedemptions($order) : 0.0);

        if (abs($sumLines) < 0.005) {
            // Geen regels om op te splitsen: val terug op het enige btw-tarief
            // van de order, of op niets als er geen btw geboekt is.
            $rates = [];
            foreach ($order->vat_percentages ?: [] as $rate => $amount) {
                if ((float) $amount != 0.0) {
                    $rates[OssVat::rateKey((float) $rate)] = true;
                }
            }

            if (abs($totalIncl) < 0.005 || count($rates) === 0) {
                return [];
            }

            return [array_key_first($rates) => $totalIncl];
        }

        // Schaal de regelbedragen naar het werkelijke ordertotaal; de rest gaat
        // naar het laatste tarief zodat de som exact gelijk blijft aan order->total.
        if (abs($sumLines - $totalIncl) >= 0.005) {
            $factor = $totalIncl / $sumLines;
            $assigned = 0.0;
            $rateKeys = array_keys($perRate);
            $lastRate = end($rateKeys);

            foreach ($perRate as $rate => $amount) {
                if ($rate === $lastRate) {
                    $perRate[$rate] = round($totalIncl - $assigned, 2);
                } else {
                    $scaled = round($amount * $factor, 2);
                    $perRate[$rate] = $scaled;
                    $assigned += $scaled;
                }
            }
        }

        return array_filter($perRate, fn ($amount) => abs($amount) >= 0.005);
    }
}
