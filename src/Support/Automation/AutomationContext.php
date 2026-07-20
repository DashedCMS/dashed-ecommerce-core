<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

use Dashed\DashedEcommerceCore\Models\Order;

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
}
