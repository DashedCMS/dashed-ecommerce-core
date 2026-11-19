<?php

namespace Dashed\DashedEcommerceCore\CustomerProfile\Sections;

use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\CustomerProfile\CustomerProfile;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class OrdersSection
{
    public const LIMIT = 25;

    public static function label(): string
    {
        return __('Bestellingen');
    }

    public static function sort(): int
    {
        return 10;
    }

    public static function forCustomer(?int $userId, ?string $email): ?array
    {
        $orders = CustomerProfile::ordersFor($userId, $email)
            ->latest('id')
            ->limit(self::LIMIT)
            ->get(['id', 'invoice_id', 'created_at', 'total', 'status', 'fulfillment_status', 'order_origin', 'credit_for_order_id']);

        return [
            'columns' => [
                'invoice' => __('Bestelnummer'),
                'date' => __('Datum'),
                'total' => __('Totaal'),
                'status' => __('Status'),
                'origin' => __('Herkomst'),
            ],
            'rows' => $orders->map(fn ($order) => [
                'invoice' => (string) ($order->invoice_id ?: $order->id) . ($order->credit_for_order_id ? ' (' . __('creditorder') . ')' : ''),
                'date' => $order->created_at?->format('d-m-Y'),
                'total' => CurrencyHelper::formatPrice($order->total),
                'status' => (string) $order->status,
                'origin' => (string) ($order->order_origin ?: 'own'),
                '_url' => rescue(fn () => OrderResource::getUrl('view', ['record' => $order->id]), null, false),
            ])->all(),
            'empty' => __('Nog geen bestellingen.'),
            'note' => $orders->count() >= self::LIMIT ? __('De laatste :aantal bestellingen.', ['aantal' => self::LIMIT]) : null,
        ];
    }
}
