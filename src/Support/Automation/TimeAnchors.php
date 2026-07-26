<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

use Carbon\Carbon;
use InvalidArgumentException;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ankermomenten voor tijd-gebaseerde automatiseringsregels ("N dagen na
 * [anker]"). Een anker is een moment dat aan een order hangt: wanneer hij is
 * aangemaakt (`created_at`) of wanneer hij voor het eerst betaald is
 * (`paid`). Er is bewust geen fulfillment-anker: orders hebben geen
 * timestamp-kolom voor fulfillmentstatussen.
 *
 * `paid` heeft geen eigen kolom — Dashed kent geen `paid_at` op orders. Het
 * betaalmoment is de vroegste `created_at` van een `dashed__order_payments`-
 * rij met `status = 'paid'` voor die order. Een order zonder betaalde
 * betaling heeft geen paid-anker: `timeFor` geeft dan null, en
 * `applyBefore`/`applyAfter` leveren zo'n order nooit op als kandidaat.
 *
 * Pure/stateloze helper: alleen lezen, geen side-effects. `applyBefore` en
 * `applyAfter` muteren enkel de meegegeven query-builder (where-clausules)
 * en worden gebruikt om de horizon-scan te beperken tot orders waarvan het
 * anker respectievelijk vóór/na een gegeven moment ligt.
 */
class TimeAnchors
{
    /**
     * Toegestane ankersleutels voor tijd-triggers.
     */
    public const KEYS = ['created_at', 'paid'];

    /**
     * Beperk de query tot orders waarvan de ankertijd op of vóór $moment ligt.
     */
    public static function applyBefore(Builder $query, string $anchor, Carbon $moment): void
    {
        self::applyBound($query, $anchor, '<=', $moment);
    }

    /**
     * Beperk de query tot orders waarvan de ankertijd op of ná $moment ligt
     * (horizon-ondergrens: voorkomt dat allang verstreken ankers alsnog als
     * kandidaat worden meegenomen).
     */
    public static function applyAfter(Builder $query, string $anchor, Carbon $moment): void
    {
        self::applyBound($query, $anchor, '>=', $moment);
    }

    /**
     * De ankertijd van één order, of null wanneer dat anker (nog) niet is
     * bereikt — bv. 'paid' voor een order zonder betaalde betaling.
     */
    public static function timeFor(Order $order, string $anchor): ?Carbon
    {
        self::guardAnchor($anchor);

        if ($anchor === 'created_at') {
            return $order->created_at;
        }

        $earliestPaidAt = $order->orderPayments()
            ->where('status', 'paid')
            ->min('created_at');

        return $earliestPaidAt ? Carbon::parse($earliestPaidAt) : null;
    }

    private static function applyBound(Builder $query, string $anchor, string $op, Carbon $moment): void
    {
        self::guardAnchor($anchor);

        $ordersTable = $query->getModel()->getTable();

        if ($anchor === 'created_at') {
            $query->where("$ordersTable.created_at", $op, $moment);

            return;
        }

        // 'paid': vroegste betaalde betaling, via een gecorreleerde subquery
        // op dashed__order_payments (geen paid_at-kolom op orders).
        $paymentsTable = 'dashed__order_payments';
        $earliestPaidSubquery = "select min(created_at) from $paymentsTable "
            ."where $paymentsTable.order_id = $ordersTable.id and status = 'paid'";

        $query->whereRaw("($earliestPaidSubquery) $op ?", [$moment])
            ->whereExists(fn ($subquery) => $subquery->from($paymentsTable)
                ->whereColumn("$paymentsTable.order_id", "$ordersTable.id")
                ->where('status', 'paid'));
    }

    private static function guardAnchor(string $anchor): void
    {
        if (! in_array($anchor, self::KEYS, true)) {
            throw new InvalidArgumentException("Onbekend tijd-anker: {$anchor}");
        }
    }
}
