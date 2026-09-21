<?php

namespace Dashed\DashedEcommerceCore\Services\OnAccount;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Openstaand saldo per klant, alleen als aggregaat in de database. Nooit
 * orders als modellen laden om ze in PHP op te tellen: het klantenscherm
 * toont dit per rij.
 */
class OnAccountBalance
{
    public static function outstandingSql(string $orderTable = 'dashed__orders'): string
    {
        return "({$orderTable}.total - (select coalesce(sum(p.amount), 0) from dashed__order_payments p where p.order_id = {$orderTable}.id and p.status = 'paid'))";
    }

    public static function open(User $user): float
    {
        return (float) Order::query()
            ->onAccountOpen()
            ->where('user_id', $user->id)
            ->sum(DB::raw(self::outstandingSql()));
    }

    public static function overdue(User $user): float
    {
        return (float) Order::query()
            ->onAccountOpen()
            ->where('user_id', $user->id)
            ->where('payment_due_at', '<', now())
            ->sum(DB::raw(self::outstandingSql()));
    }

    public static function hasOverdue(User $user, int $days): bool
    {
        return Order::query()
            ->onAccountOpen()
            ->where('user_id', $user->id)
            ->where('payment_due_at', '<', now()->subDays($days))
            ->exists();
    }

    public static function addOpenColumns(Builder $usersQuery): Builder
    {
        $table = $usersQuery->getModel()->getTable();

        $base = fn () => Order::query()
            ->onAccountOpen()
            ->whereColumn('dashed__orders.user_id', "{$table}.id")
            ->selectRaw('coalesce(sum('.self::outstandingSql().'), 0)');

        if (empty($usersQuery->getQuery()->columns)) {
            $usersQuery->select("{$table}.*");
        }

        return $usersQuery->addSelect([
            'on_account_open' => $base(),
            'on_account_overdue' => $base()->where('dashed__orders.payment_due_at', '<', now()),
        ]);
    }
}
