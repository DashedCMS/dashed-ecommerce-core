<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * De basisquery waar elke analyse op staat: orderregels van betaalde orders
 * in een periode, op één site.
 *
 * Op één plek zodat "wat telt mee als omzet" niet per analyse kan gaan
 * afwijken. Betaald is Order::isPaid() en de datum is orders.created_at,
 * gelijk aan de bestaande statistiekpagina's.
 */
class OrderLineQuery
{
    public static function orders(AnalysisPeriod $period, string $siteId): EloquentBuilder
    {
        return Order::query()
            ->where('site_id', $siteId)
            ->isPaid()
            ->whereBetween('created_at', [$period->start, $period->end]);
    }

    /**
     * Orderregels met de order erbij gejoind als `o`. Alleen regels die aan
     * een product hangen; verzend- en betaalkostenregels hebben geen
     * product_id en horen niet in productstatistiek thuis.
     */
    public static function lines(AnalysisPeriod $period, string $siteId): QueryBuilder
    {
        return DB::table('dashed__order_products as op')
            ->join('dashed__orders as o', 'o.id', '=', 'op.order_id')
            ->whereNull('op.deleted_at')
            ->whereNotNull('op.product_id')
            ->whereIn('o.id', self::orders($period, $siteId)->select('dashed__orders.id'));
    }
}
