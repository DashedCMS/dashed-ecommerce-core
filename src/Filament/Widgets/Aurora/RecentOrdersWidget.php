<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Aurora;

use Filament\Widgets\Widget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

/**
 * De laatste vijf betaalde of lopende bestellingen, met klant, bedrag en
 * status. Eén query; de status komt uit Order::orderStatus(), dezelfde
 * labels als in de orderlijst.
 */
class RecentOrdersWidget extends Widget
{
    public const LIMIT = 5;

    public const STATUSES = ['paid', 'partially_paid', 'waiting_for_confirmation', 'pending'];

    protected string $view = 'dashed-ecommerce-core::filament.widgets.aurora.recent-orders';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) rescue(fn () => OrderResource::canViewAny(), false, false);
    }

    public static function toneFor(?string $color): string
    {
        return match ($color) {
            'success' => 'mint',
            'danger' => 'red',
            'warning' => 'amber',
            'purple' => 'purple',
            'primary', 'info' => 'blue',
            default => 'neutral',
        };
    }

    /** @return array<int, array{number:string,customer:string,total:string,status:string,tone:string,fulfillment:?string,url:string}> */
    public function getOrders(): array
    {
        return Order::query()
            ->whereIn('status', self::STATUSES)
            ->latest('created_at')
            ->latest('id')
            ->limit(self::LIMIT)
            // Order laadt standaard regels en betalingen mee; hier is niets daarvan nodig.
            ->setEagerLoads([])
            ->get()
            ->map(function (Order $order) {
                $status = $order->orderStatus();
                $fulfillment = in_array($order->status, Order::PAID_STATUSES, true)
                    ? (Orders::getFulfillmentStatusses()[$order->fulfillment_status] ?? null)
                    : null;

                return [
                    'number' => $order->invoice_id && ! in_array($order->invoice_id, ['PROFORMA', 'RETURN'], true)
                        ? (string) $order->invoice_id
                        : '#'.$order->id,
                    'customer' => trim((string) $order->name) !== '' ? (string) $order->name : (string) $order->email,
                    'total' => CurrencyHelper::formatPrice($order->total),
                    'status' => __($status['status']),
                    'tone' => self::toneFor($status['color'] ?? null),
                    'fulfillment' => $fulfillment ? __($fulfillment) : null,
                    'url' => OrderResource::getUrl('view', ['record' => $order]),
                ];
            })
            ->all();
    }
}
