<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

/**
 * The tabs above the order list, defined strictly on existing order columns.
 * One definition for both the tab query and its counter, so the number on a
 * tab is always the number of rows the tab shows.
 *
 * - alle:      no extra condition (only the table filters apply).
 * - nieuw:     status in Order::PAID_STATUSES and fulfillment_status = unhandled
 *              (the same rows as Order::unhandled() and the navigation badge).
 * - inpakken:  status in Order::PAID_STATUSES and fulfillment_status = in_treatment.
 * - verzenden: status in Order::PAID_STATUSES and fulfillment_status in packed, ready_for_pickup.
 * - afgerond:  status in Order::PAID_STATUSES and fulfillment_status in shipped, handled, partially_handled.
 * - retouren:  status = return (credit orders) or an open OrderReturn
 *              (OrderReturn::open(): not rejected, handled or closed).
 *              The DEFAULT value of the status filter is ignored on this tab,
 *              because it (paid, partially_paid, waiting_for_confirmation)
 *              would hide every credit order; a status the admin chose applies.
 */
class OrderListTabs
{
    public const ALL = 'alle';

    public const RETURNS = 'retouren';

    /**
     * Fulfillment statuses per fulfillment tab; status is always Order::PAID_STATUSES.
     *
     * @var array<string, array<int, string>>
     */
    public const FULFILLMENT_TABS = [
        'nieuw' => ['unhandled'],
        'inpakken' => ['in_treatment'],
        'verzenden' => ['packed', 'ready_for_pickup'],
        'afgerond' => ['shipped', 'handled', 'partially_handled'],
    ];

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ALL => __('Alle bestellingen'),
            'nieuw' => __('Nieuw'),
            'inpakken' => __('Inpakken'),
            'verzenden' => __('Verzenden'),
            'afgerond' => __('Afgerond'),
            self::RETURNS => __('Retouren'),
        ];
    }

    public static function apply(string $tab, Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        if (isset(self::FULFILLMENT_TABS[$tab])) {
            return $query
                ->whereIn("{$table}.status", Order::PAID_STATUSES)
                ->whereIn("{$table}.fulfillment_status", self::FULFILLMENT_TABS[$tab]);
        }

        if ($tab === self::RETURNS) {
            return $query->where(fn (Builder $q) => $q
                ->where("{$table}.status", 'return')
                ->orWhereHas('orderReturns', fn (Builder $r) => $r->open()));
        }

        return $query;
    }

    /**
     * Counts for every tab except retouren, in one aggregate query over the
     * given (already filtered) list query.
     *
     * @return array<string, int>
     */
    public static function countFulfillmentTabs(Builder $query): array
    {
        // Wrapped as a subquery so any join or subselect a filter added to
        // the list query cannot clash with the aggregate.
        $selects = ['count(*) as ' . self::ALL];
        $bindings = [];

        foreach (self::FULFILLMENT_TABS as $tab => $fulfillmentStatuses) {
            $selects[] = sprintf(
                'sum(case when status in (%1$s) and fulfillment_status in (%2$s) then 1 else 0 end) as %3$s',
                implode(', ', array_fill(0, count(Order::PAID_STATUSES), '?')),
                implode(', ', array_fill(0, count($fulfillmentStatuses), '?')),
                $tab,
            );
            array_push($bindings, ...Order::PAID_STATUSES, ...$fulfillmentStatuses);
        }

        // Only the two columns the aggregate needs, fully qualified, so a
        // join added by a filter cannot make status ambiguous.
        $table = $query->getModel()->getTable();
        $inner = $query->toBase()->reorder();
        $inner->columns = null;
        $inner->select(["{$table}.status", "{$table}.fulfillment_status"]);

        $row = DB::connection($query->getModel()->getConnectionName())
            ->query()
            ->fromSub($inner, 'tab_orders')
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        $counts = [];
        foreach (array_merge([self::ALL], array_keys(self::FULFILLMENT_TABS)) as $tab) {
            $counts[$tab] = (int) ($row->{$tab} ?? 0);
        }

        return $counts;
    }

    public static function countReturns(Builder $query): int
    {
        return self::apply(self::RETURNS, $query)->reorder()->count();
    }
}
