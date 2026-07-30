<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

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
    /**
     * Regels die geen product zijn maar kosten. Een procentuele kortingscode
     * geldt in de winkelwagen alleen over cart items, nooit over verzend- of
     * betaalkosten; die uitzondering geldt hier net zo.
     */
    public const COST_SKUS = ['shipping_costs', 'payment_costs', 'product_costs'];

    public static function recalculate(Order $order): void
    {
        $inclusive = (bool) Customsetting::get('taxes_prices_include_taxes');

        $subtotal = 0.0;
        $vatPerRate = [];
        $lines = [];

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

            $lines[] = [
                'price' => $price,
                'quantity' => (int) $line->quantity,
                'product_id' => $line->product_id,
                'sku' => $line->sku,
            ];
        }

        $discount = self::discountForLines($order, $lines);

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

    /**
     * Het kortingsbedrag dat bij deze regels hoort.
     *
     * Een kortingscode met een vast bedrag blijft staan zoals hij op de order
     * is vastgelegd: dat bedrag is bij het afrekenen afgesproken en hoort niet
     * mee te bewegen met een wijziging. Een procentuele code moet dat juist
     * wél, anders houdt een order met 10% korting na het toevoegen van een
     * product zijn oude euro-bedrag en betaalt de klant te veel.
     *
     * Een korting kan nooit groter zijn dan het subtotaal. Wordt een order zo
     * aangepast dat er minder overblijft dan de korting, dan zakt de korting
     * mee naar het subtotaal; zonder die aftopping zou het totaal negatief
     * worden terwijl de btw op nul blijft staan.
     *
     * Elke regel is een array met price, quantity, product_id en sku, zodat
     * zowel opgeslagen orderregels als de nog niet opgeslagen regels uit het
     * wijzigformulier hier doorheen kunnen.
     *
     * @param  array<int, array{price: float, quantity: int, product_id: int|null, sku: string|null}>  $lines
     */
    public static function discountForLines(Order $order, array $lines): float
    {
        $subtotal = array_sum(array_map(fn (array $line): float => (float) ($line['price'] ?? 0), $lines));

        $discountCode = $order->discountCode;

        $discount = $discountCode && $discountCode->type === 'percentage'
            ? self::percentageDiscountForLines($discountCode, $lines)
            : (float) ($order->discount ?? 0);

        return round(min($discount, $subtotal), 2);
    }

    /**
     * Zelfde regels als Product::getShoppingCartItemPrice() stap 10, de plek
     * waar de winkelwagen een procentuele code toepast: per regel bepalen of de
     * code geldig is (op alles, of alleen op bepaalde categorieën/producten) en
     * dan per stuk afronden voordat er weer met het aantal vermenigvuldigd
     * wordt. Een regel zonder gekoppeld product telt daar als custom item: die
     * krijgt alleen korting wanneer de code niet tot categorieën of producten
     * beperkt is.
     *
     * @param  array<int, array{price: float, quantity: int, product_id: int|null, sku: string|null}>  $lines
     */
    protected static function percentageDiscountForLines(DiscountCode $discountCode, array $lines): float
    {
        $percentage = (float) $discountCode->discount_percentage;

        if ($percentage <= 0) {
            return 0.0;
        }

        $discount = 0.0;

        foreach ($lines as $line) {
            if (in_array($line['sku'] ?? null, self::COST_SKUS, true)) {
                continue;
            }

            $price = (float) ($line['price'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            if ($price <= 0 || $quantity <= 0) {
                continue;
            }

            if (! self::codeAppliesToLine($discountCode, $line['product_id'] ?? null)) {
                continue;
            }

            $unitPrice = $price / $quantity;
            $discountedUnitPrice = round($unitPrice * (100 - $percentage) / 100, 2);

            $discount += $price - ($discountedUnitPrice * $quantity);
        }

        return max($discount, 0.0);
    }

    protected static function codeAppliesToLine(DiscountCode $discountCode, ?int $productId): bool
    {
        $product = $productId ? Product::find($productId) : null;

        if (! $product) {
            return ! in_array($discountCode->valid_for, ['categories', 'products'], true);
        }

        if ($discountCode->valid_for === 'categories') {
            return $discountCode->productCategories()
                ->whereIn('product_category_id', $product->productCategories()->pluck('product_category_id'))
                ->exists();
        }

        if ($discountCode->valid_for === 'products') {
            return $discountCode->products()->where('product_id', $product->id)->exists();
        }

        return true;
    }
}
