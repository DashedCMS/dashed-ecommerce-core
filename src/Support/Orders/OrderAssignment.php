<?php

namespace Dashed\DashedEcommerceCore\Support\Orders;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

/**
 * Toewijzing van een bestelling aan een medewerker (assigned_to_user_id).
 *
 * Automatisch: de eerste medewerker die een orderlogregel veroorzaakt wordt de
 * eigenaar; een bestaande toewijzing wordt nooit automatisch overschreven.
 * Handmatig: overnemen (overschrijft) en vrijgeven (maakt leeg).
 *
 * Alle schrijfacties zijn query-updates, zodat er geen Order-events of
 * -observers afgaan (en er dus geen lus via nieuwe logregels kan ontstaan).
 */
class OrderAssignment
{
    /** Logregels die nooit tot een automatische toewijzing leiden. */
    public const EXCLUDED_TAGS = [
        'order.created',
        'order.created.by.admin',
        'abandoned-cart.converted',
        'order.email.sent',
        'order.assignment.taken-over',
        'order.assignment.released',
    ];

    public static function assignFromLog(OrderLog $log): void
    {
        if (! $log->order_id || ! $log->user_id || $log->is_system) {
            return;
        }

        if (in_array($log->tag, self::EXCLUDED_TAGS, true)) {
            return;
        }

        Order::withTrashed()
            ->whereKey($log->order_id)
            ->whereNull('assigned_to_user_id')
            ->update([
                'assigned_to_user_id' => $log->user_id,
                'assigned_at' => now(),
            ]);
    }

    /** Overnemen: de bestelling gaat naar deze medewerker, ook als iemand anders hem had. */
    public static function takeOver(Order $order, int $userId): void
    {
        if ((int) $order->assigned_to_user_id === $userId) {
            return;
        }

        self::write($order, $userId, now());

        OrderLog::createLog(orderId: $order->id, tag: 'order.assignment.taken-over', userId: $userId);
    }

    /** Vrijgeven: niemand is meer eigenaar. */
    public static function release(Order $order, ?int $userId): void
    {
        if ($order->assigned_to_user_id === null) {
            return;
        }

        self::write($order, null, null);

        OrderLog::createLog(orderId: $order->id, tag: 'order.assignment.released', userId: $userId);
    }

    private static function write(Order $order, ?int $userId, $assignedAt): void
    {
        $values = [
            'assigned_to_user_id' => $userId,
            'assigned_at' => $assignedAt,
        ];

        Order::withTrashed()->whereKey($order->getKey())->update($values);

        // Het meegegeven model bijwerken zonder het als gewijzigd te markeren.
        $order->forceFill($values)->syncOriginalAttributes(array_keys($values));
        $order->unsetRelation('assignedUser');
    }
}
