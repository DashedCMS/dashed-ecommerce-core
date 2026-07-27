<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;

/**
 * Bouwt de waardecontext die ConditionEvaluator::matches() nodig heeft, voor
 * de conditie-velden die OrderAutomationTriggers::orderConditionFields()
 * declareert. Twee velden — `product_count` en `has_discount_code` — hebben
 * in die registry bewust geen resolver (om DB-queries bij elke boot te
 * vermijden); die worden hier uit de Order berekend.
 *
 * In tegenstelling tot ConditionEvaluator is dit géén pure functie: het
 * leest relaties van een echte Order (orderProducts, de payment-method-
 * accessor) en mag dus DB-calls doen. Dat is prima zolang de evaluator zelf
 * puur blijft.
 *
 * De kernvelden hieronder winnen altijd van `$extra`: AutomationTrigger-
 * Subscriber::extraContext() leest alle publieke, niet-Model-properties van
 * het trigger-event en geeft die door als `$extra`. Zonder die precedentie
 * zou een toekomstig event met een publieke `$status`/`$total`-property
 * stilzwijgend de conditie-semantiek voor élke regel op dat kernveld
 * veranderen — vandaar dat `$extra` alleen aanvult, nooit overschrijft.
 */
class AutomationContext
{
    /**
     * @param  array<string, mixed>  $extra  trigger-specifieke velden, bv. old_status/new_status bij order.fulfillment_changed
     * @return array<string, mixed>
     */
    public static function forOrder(Order $order, array $extra = []): array
    {
        $core = [
            'total' => (float) $order->total,
            'country' => $order->country,
            'origin' => $order->order_origin,
            'payment_method' => $order->payment_method,
            'status' => $order->status,
            'fulfillment_status' => $order->fulfillment_status,
            'product_count' => (int) $order->orderProducts->sum('quantity'),
            'has_discount_code' => self::hasDiscountCode($order),
        ];

        // `+` (array-union), niet spread: bestaande kernvelden winnen altijd,
        // `$extra` mag alleen nieuwe velden toevoegen.
        return $core + $extra;
    }

    /**
     * Eén order-brede kortingscode (discount_code_id) of één of meer
     * POS-kortingscodes/cadeaubonnen (applied_discount_codes) telt allebei
     * als "heeft kortingscode".
     */
    private static function hasDiscountCode(Order $order): bool
    {
        if (filled($order->discount_code_id)) {
            return true;
        }

        return is_array($order->applied_discount_codes) && count($order->applied_discount_codes) > 0;
    }

    /**
     * Context voor voorraad-triggers (stock.low/stock.back e.d.): het Product
     * zelf is het onderwerp.
     *
     * @return array<string, mixed>
     */
    public static function forProduct(Product $product): array
    {
        return [
            'stock' => (int) $product->stock,
            'price' => (float) $product->price,
            'name' => (string) $product->name,
            'sku' => (string) $product->sku,
        ];
    }

    /**
     * Context voor klant-triggers (customer.new/customer.nth_order e.d.): de
     * Order is het onderwerp (ook voor gasten, die geen User hebben), maar de
     * conditie-velden gaan over de klánt erachter. Die klant is `user_id`
     * (geregistreerd) of, als die ontbreekt, `email` (gast) — geen bredere
     * naam-matching zoals Order::scopeForCustomerOf(), want die telt bewust
     * ruimer (ook first_name+last_name) dan wat hier gevraagd wordt.
     *
     * `order_count`/`total_spend` tellen over alle bestellingen van die klant
     * (inclusief `$order` zelf), niet begrensd op `created_at` t.o.v. deze
     * order — wél begrensd op `site_id`: een regel van site A mag nooit
     * meetellen met bestellingen van site B (site-bewuste beslissing van de
     * gebruiker, fix-ronde 1). Eenzelfde klant die op meerdere sites bestelt
     * telt dus per site apart.
     *
     * @return array<string, mixed>
     */
    public static function forCustomer(Order $order): array
    {
        $ordersOfCustomer = $order->user_id
            ? Order::query()->where('user_id', $order->user_id)
            : Order::query()->where('email', $order->email);

        $ordersOfCustomer->where('site_id', $order->site_id);

        return [
            'order_count' => (int) (clone $ordersOfCustomer)->count(),
            'total_spend' => (float) (clone $ordersOfCustomer)->sum('total'),
            'email' => (string) $order->email,
            'is_registered' => filled($order->user_id),
        ];
    }

    /**
     * Dispatcht op het type van het onderwerp. Klant-context hoort óók bij
     * een Order-onderwerp (klant-triggers gebruiken de Order als onderwerp),
     * maar dat is geen taak van deze dispatcher: de subscriber geeft voor een
     * klant-trigger `forCustomer($order)` zelf mee als `$extra` (zie Task 2/3).
     * Onbekend subject-type → `[]` (fail-closed): een regel op een type dat
     * we niet kennen mag nooit stilzwijgend op de verkeerde velden matchen.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function for(Model $subject, array $extra = []): array
    {
        return match ($subject::class) {
            Order::class => self::forOrder($subject, $extra),
            Product::class => self::forProduct($subject),
            default => [],
        };
    }
}
