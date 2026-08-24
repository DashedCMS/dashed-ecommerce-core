<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;

/**
 * Herberekent de totalen van een order op basis van zijn eigen orderregels.
 *
 * De regelprijs is de prijs ná een procentuele kortingscode. Dat is geen keuze
 * van deze klasse maar de conventie van de hele webshop: Checkout::createOrder()
 * zet per regel `price = discountedPrice` en `discount = originalPrice -
 * discountedPrice`, omdat Product::getShoppingCartItemPrice() een procentuele
 * code al per regel verwerkt. Een vast bedrag (of cadeaubon) gaat er juist niet
 * per regel af; dat staat alleen als `discount` op de order zelf.
 *
 * Daaruit volgt:
 *
 *   subtotal = som(regelprijs + regelkorting)
 *   discount = som(regelkorting) + de vaste korting op orderniveau
 *   total    = som(regelprijs)   - diezelfde vaste korting
 *
 * Wie de regelprijs als prijs vóór korting leest en de korting van het
 * regeltotaal aftrekt, trekt een procentuele code dus een tweede keer af.
 */
class OrderTotalsCalculator
{
    public static function recalculate(Order $order): void
    {
        $inclusive = (bool) Customsetting::get('taxes_prices_include_taxes');

        $vatPerRate = [];
        $lines = [];

        // Bewust via de query en niet via de eager-geladen relatie: die kan
        // verouderd zijn nadat regels net herschreven zijn.
        foreach ($order->orderProducts()->get() as $line) {
            $price = (float) $line->price;
            $rate = (float) ($line->vat_rate ?? 0);

            $vat = $inclusive
                ? $price / (100 + $rate) * $rate
                : $price / 100 * $rate;

            $key = (string) (int) round($rate);
            $vatPerRate[$key] = ($vatPerRate[$key] ?? 0.0) + $vat;

            $lines[] = [
                'price' => $price,
                'discount' => (float) ($line->discount ?? 0),
            ];
        }

        $breakdown = self::breakdownForLines($order, $lines);

        // De btw hoort te gaan over wat de klant betaalt. Een regelkorting zit
        // al in de regelprijs verwerkt en is hierboven dus al verrekend; alleen
        // een vaste korting op orderniveau drukt de btw nog, en die drukt hem
        // proportioneel, ook bij gemengde tarieven.
        $factor = $breakdown['net'] > 0 ? $breakdown['total'] / $breakdown['net'] : 1.0;

        $order->subtotal = $breakdown['subtotal'];
        $order->discount = $breakdown['discount'];
        $order->total = $breakdown['total'];
        $order->btw = round(array_sum($vatPerRate) * $factor, 2);
        $order->vat_percentages = array_map(
            fn (float $amount): float => round($amount * $factor, 2),
            $vatPerRate
        );
        $order->save();

        // Een afgetopte korting laat geld verdwijnen: bij een cadeaubon staat er
        // in used_amount nog het volle bedrag terwijl de klant het niet meer
        // krijgt. Dit terugboeken gebeurt bewust niet automatisch, maar het mag
        // ook niet ongemerkt gebeuren, dus het komt in het orderlogboek te staan.
        if ($breakdown['reduced_by'] > 0.005) {
            OrderLog::createLog(
                orderId: $order->id,
                tag: 'order.discount.capped',
                note: self::cappedDiscountSentence($order, $breakdown),
            );
        }
    }

    /**
     * Eén zin over een afgetopte korting, voor het orderlogboek én voor de
     * bevestigingsstap van het wijzigscherm, zodat beide precies hetzelfde
     * zeggen. Bij een cadeaubon staat er expliciet bij dat het om echt
     * klantsaldo gaat: dat is het enige geval waarin er geld van de klant zelf
     * in verdwijnt.
     *
     * @param  array{discount: float, uncapped: float, reduced_by: float}  $breakdown
     */
    public static function cappedDiscountSentence(Order $order, array $breakdown): string
    {
        $discountCode = $order->discountCode;

        $sentence = __('Korting verlaagd van :van naar :naar (:minder minder), omdat de korting niet groter kan zijn dan het subtotaal van de bestelling.', [
            'van' => CurrencyHelper::formatPrice($breakdown['uncapped']),
            'naar' => CurrencyHelper::formatPrice($breakdown['discount']),
            'minder' => CurrencyHelper::formatPrice($breakdown['reduced_by']),
        ]);

        if ($discountCode && $discountCode->is_giftcard) {
            return $sentence . ' ' . __('Dit is cadeaubon :code: dat saldo wordt niet automatisch teruggeboekt, corrigeer het handmatig op de cadeaubon.', ['code' => $discountCode->code]);
        }

        return $sentence;
    }

    /**
     * De totalen die bij deze regels horen.
     *
     * Elke regel is een array met price (wat de klant voor die regel betaalt) en
     * discount (wat er op die regel is afgegaan), zodat zowel opgeslagen
     * orderregels als de nog niet opgeslagen regels uit het wijzigformulier hier
     * doorheen kunnen.
     *
     * Een vaste korting blijft staan zoals hij op de order is vastgelegd: dat
     * bedrag is bij het afrekenen afgesproken en hoort niet mee te bewegen met
     * een wijziging. Hij kan alleen nooit groter zijn dan wat er aan regels
     * overblijft; wordt een order zo aangepast dat er minder overblijft, dan
     * zakt de korting mee. Zonder die aftopping zou het totaal negatief worden
     * terwijl de btw op nul blijft staan.
     *
     * @param  array<int, array{price?: float|null, discount?: float|null}>  $lines
     * @return array{net: float, subtotal: float, discount: float, total: float, uncapped: float, reduced_by: float}
     */
    public static function breakdownForLines(Order $order, array $lines): array
    {
        $net = 0.0;
        $lineDiscount = 0.0;

        foreach ($lines as $line) {
            $net += (float) ($line['price'] ?? 0);
            $lineDiscount += (float) ($line['discount'] ?? 0);
        }

        $net = round($net, 2);
        $lineDiscount = round($lineDiscount, 2);

        $uncapped = round(self::orderLevelDiscount($order), 2);
        $capped = round(min($uncapped, $net), 2);

        return [
            'net' => $net,
            'subtotal' => round($net + $lineDiscount, 2),
            'discount' => round($lineDiscount + $capped, 2),
            'total' => round($net - $capped, 2),
            // uncapped telt de regelkortingen mee zodat hij naast 'discount'
            // gelegd kan worden in de melding over een aftopping; alleen de
            // korting op orderniveau kan afgetopt worden, want een regelkorting
            // zit al in de regelprijs verwerkt.
            'uncapped' => round($lineDiscount + $uncapped, 2),
            'reduced_by' => round($uncapped - $capped, 2),
        ];
    }

    /**
     * Het kortingspercentage dat de code van deze order op dit product geeft, of
     * 0 wanneer er niets geldt.
     *
     * Zelfde afweging als Product::getShoppingCartItemPrice() stap 10, de plek
     * waar de winkelwagen een procentuele code toepast: de code kan tot
     * bepaalde categorieën of producten beperkt zijn, en een regel zonder
     * gekoppeld product telt daar als custom item dat alleen korting krijgt als
     * de code niet zo beperkt is.
     *
     * Alleen bedoeld om een prijs uit te rekenen die de klant zou betalen (het
     * wijzigscherm vult daarmee de regelprijs bij het kiezen van een product).
     * De totalen van een bestaande order lopen hier niet doorheen: daar zit het
     * percentage al in de regelprijs verwerkt.
     */
    public static function percentageForProduct(Order $order, ?int $productId): float
    {
        $discountCode = $order->discountCode;

        if (! $discountCode || $discountCode->type !== 'percentage') {
            return 0.0;
        }

        $percentage = (float) $discountCode->discount_percentage;

        if ($percentage <= 0) {
            return 0.0;
        }

        $product = $productId ? Product::find($productId) : null;

        if (! $product) {
            return in_array($discountCode->valid_for, ['categories', 'products'], true) ? 0.0 : $percentage;
        }

        if ($discountCode->valid_for === 'categories') {
            return $discountCode->productCategories()
                ->whereIn('product_category_id', $product->productCategories()->pluck('product_category_id'))
                ->exists() ? $percentage : 0.0;
        }

        if ($discountCode->valid_for === 'products') {
            return $discountCode->products()->where('product_id', $product->id)->exists() ? $percentage : 0.0;
        }

        return $percentage;
    }

    /**
     * De korting die niet in de regelprijzen zit en er dus nog van het totaal af
     * moet.
     *
     * Bij een procentuele code is dat niets: die is bij het afrekenen al per
     * regel verwerkt (Product::getShoppingCartItemPrice() stap 10) en zou er
     * hier een tweede keer afgaan. Bij een vast bedrag, een cadeaubon of een
     * handmatig ingevulde korting staat het bedrag alleen op de order zelf en
     * gaat het er hier dus wél af.
     */
    protected static function orderLevelDiscount(Order $order): float
    {
        if ($order->discountCode?->type === 'percentage') {
            return 0.0;
        }

        return (float) ($order->discount ?? 0);
    }
}
