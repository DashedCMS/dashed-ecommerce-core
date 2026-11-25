<?php

namespace Dashed\DashedEcommerceCore\Support\Orders;

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

/**
 * Toewijzing van een bestelling aan een medewerker (assigned_to_user_id).
 *
 * Automatisch: de eerste medewerker die een orderlogregel veroorzaakt wordt de
 * eigenaar; een bestaande toewijzing wordt nooit automatisch overschreven.
 * Alleen medewerkers (paneelaccounts) tellen: klanten delen de auth-guard en
 * loggen zelf ook regels (bijv. order.paid na afrekenen), en ook een
 * medewerker die de klant van de bestelling zelf is wordt niet toegewezen.
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
        'order.assignment.assigned',
        'order.assignment.released',
        'order.marked-as-paid',
        'order.payment_link.sent',
        'order.payment-reminder.manual',
    ];

    public static function assignFromLog(OrderLog $log): void
    {
        if (! $log->order_id || ! $log->user_id || $log->is_system) {
            return;
        }

        if (in_array($log->tag, self::EXCLUDED_TAGS, true)) {
            return;
        }

        $userId = (int) $log->user_id;

        if (! self::isStaff($userId)) {
            return;
        }

        // Nooit aan de eigen klant van de bestelling (ook niet als die medewerker is).
        Order::withTrashed()
            ->whereKey((int) $log->order_id)
            ->whereNull('assigned_to_user_id')
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $userId))
            ->update([
                'assigned_to_user_id' => $userId,
                'assigned_at' => now(),
            ]);
    }

    /**
     * Medewerker = account met paneelrechten (superadmin of minstens één
     * rol), zelfde regel als User::hasPanelAccess(). Eén query.
     */
    private static function isStaff(int $userId): bool
    {
        return User::query()
            ->whereKey($userId)
            ->panelUsers()
            ->exists();
    }

    /**
     * Wijst de bestelling toe aan deze medewerker als er nog niemand is
     * toegewezen (atomair, zonder model-events). Het meegegeven model wordt
     * bijgewerkt. Geeft true terug als de toewijzing nu is gezet.
     */
    public static function assignIfUnassigned(Order $order, int $userId): bool
    {
        if ($order->assigned_to_user_id !== null) {
            return false;
        }

        $assignedAt = now();

        if (! self::assignIfUnassignedId((int) $order->getKey(), $userId, $assignedAt)) {
            return false;
        }

        self::syncModel($order, ['assigned_to_user_id' => $userId, 'assigned_at' => $assignedAt]);

        return true;
    }

    private static function assignIfUnassignedId(int $orderId, int $userId, $assignedAt = null): bool
    {
        return Order::withTrashed()
            ->whereKey($orderId)
            ->whereNull('assigned_to_user_id')
            ->update([
                'assigned_to_user_id' => $userId,
                'assigned_at' => $assignedAt ?? now(),
            ]) > 0;
    }

    /**
     * Overnemen: de bestelling gaat naar deze medewerker, ook als iemand anders
     * hem had. Wijst iemand anders (de actor) hem toe, dan logt de actor met
     * tag order.assignment.assigned en de naam van de nieuwe eigenaar als notitie.
     */
    public static function takeOver(Order $order, int $userId, ?int $actorId = null): void
    {
        if ((int) $order->assigned_to_user_id === $userId) {
            return;
        }

        self::write($order, $userId, now());

        if ($actorId !== null && $actorId !== $userId) {
            OrderLog::createLog(
                orderId: $order->id,
                tag: 'order.assignment.assigned',
                note: User::find($userId)?->name,
                userId: $actorId,
            );

            return;
        }

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

        self::syncModel($order, $values);
    }

    /** Het meegegeven model bijwerken zonder het als gewijzigd te markeren. */
    private static function syncModel(Order $order, array $values): void
    {
        $order->forceFill($values)->syncOriginalAttributes(array_keys($values));
        $order->unsetRelation('assignedUser');
    }
}
