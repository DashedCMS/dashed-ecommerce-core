<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Carbon;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

/**
 * De voortgangsbalk bovenaan de bestelpagina: geplaatst, betaald, ingepakt,
 * verzonden, afgehandeld en eventueel een retour. De stand komt uit de status
 * van de bestelling zelf; de datum per stap uit de oudste logregel met de
 * bijbehorende tag, want een eigen kolom per moment is er niet.
 */
class OrderProgress
{
    public const DONE = 'done';
    public const CURRENT = 'current';
    public const TODO = 'todo';
    public const WARNING = 'warning';
    public const DANGER = 'danger';

    private const FULFILLMENT_RANK = [
        'unhandled' => 0,
        'in_treatment' => 1,
        'packed' => 2,
        'ready_for_pickup' => 3,
        'shipped' => 3,
        'handled' => 4,
    ];

    private const TAGS = [
        'placed' => ['order.created', 'order.created.by.admin', 'order.created.by.channable'],
        'paid' => ['order.paid', 'order.marked-as-paid', 'order.partially_paid'],
        'cancelled' => ['order.cancelled', 'order.system.cancelled'],
        'packed' => ['order.changed-fulfillment-status-to-packed'],
        'shipped' => ['order.changed-fulfillment-status-to-shipped', 'order.changed-fulfillment-status-to-ready_for_pickup'],
        'handled' => ['order.changed-fulfillment-status-to-handled'],
    ];

    /**
     * @return array<int, array{label: string, state: string, date: ?Carbon}>
     */
    public static function for(Order $order): array
    {
        $dates = self::dates($order);
        $rank = self::FULFILLMENT_RANK[$order->fulfillment_status] ?? 0;
        $statuses = Orders::getFulfillmentStatusses();

        $orderStatus = $order->orderStatus();
        $paymentState = match ($orderStatus['color']) {
            'success' => self::DONE,
            'danger' => self::DANGER,
            'warning' => self::WARNING,
            default => self::CURRENT,
        };

        $steps = [
            ['label' => __('Geplaatst'), 'state' => self::DONE, 'date' => $dates['placed'] ?? $order->created_at],
            [
                'label' => __($orderStatus['status']),
                'state' => $paymentState,
                'date' => $paymentState === self::DANGER ? ($dates['cancelled'] ?? null) : ($paymentState === self::CURRENT ? null : ($dates['paid'] ?? null)),
            ],
        ];

        if ($paymentState !== self::DANGER) {
            $steps[] = [
                'label' => $rank === 1 ? ($statuses['in_treatment'] ?? __('In behandeling')) : ($statuses['packed'] ?? __('Ingepakt')),
                'state' => $rank >= 2 ? self::DONE : ($rank === 1 ? self::CURRENT : self::TODO),
                'date' => $rank >= 2 ? ($dates['packed'] ?? null) : null,
            ];
            $steps[] = [
                'label' => $order->fulfillment_status === 'ready_for_pickup' ? ($statuses['ready_for_pickup'] ?? __('Klaar om opgehaald te worden')) : ($statuses['shipped'] ?? __('Verzonden')),
                'state' => $rank >= 3 ? self::DONE : self::TODO,
                'date' => $rank >= 3 ? ($dates['shipped'] ?? null) : null,
            ];
            $steps[] = [
                'label' => $statuses['handled'] ?? __('Afgehandeld'),
                'state' => $rank >= 4 ? self::DONE : self::TODO,
                'date' => $rank >= 4 ? ($dates['handled'] ?? null) : null,
            ];
        }

        $return = $order->orderReturns()->orderByDesc('id')->first();
        if ($return) {
            $open = ! in_array($return->status, [OrderReturn::STATUS_REJECTED, OrderReturn::STATUS_HANDLED, OrderReturn::STATUS_CLOSED], true);
            $steps[] = [
                'label' => __('Retour: :status', ['status' => mb_strtolower($return->statusLabel())]),
                'state' => $open ? self::WARNING : self::DONE,
                'date' => $return->created_at,
            ];
        }

        return $steps;
    }

    /**
     * @return array<string, Carbon>
     */
    private static function dates(Order $order): array
    {
        $logs = $order->logs()
            ->whereIn('tag', array_merge(...array_values(self::TAGS)))
            ->orderBy('created_at')
            ->get(['tag', 'created_at']);

        $dates = [];
        foreach (self::TAGS as $step => $tags) {
            $log = $logs->first(fn ($log) => in_array($log->tag, $tags, true));
            if ($log) {
                $dates[$step] = $log->created_at;
            }
        }

        return $dates;
    }
}
