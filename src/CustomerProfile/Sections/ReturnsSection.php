<?php

namespace Dashed\DashedEcommerceCore\CustomerProfile\Sections;

use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\CustomerProfile\CustomerProfile;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;

class ReturnsSection
{
    public static function label(): string
    {
        return __('Retouren');
    }

    public static function sort(): int
    {
        return 20;
    }

    public static function forCustomer(?int $userId, ?string $email): ?array
    {
        $orderIds = CustomerProfile::ordersFor($userId, $email)->select('id');

        $returns = OrderReturn::query()
            ->whereIn('order_id', $orderIds)
            ->with('order:id,invoice_id')
            ->latest('id')
            ->limit(25)
            ->get();

        return [
            'stats' => [
                ['label' => __('Retouren'), 'value' => (string) OrderReturn::query()->whereIn('order_id', CustomerProfile::ordersFor($userId, $email)->select('id'))->count()],
            ],
            'columns' => [
                'order' => __('Bestelling'),
                'date' => __('Aangemeld op'),
                'status' => __('Status'),
            ],
            'rows' => $returns->map(fn ($return) => [
                'order' => (string) ($return->order?->invoice_id ?: $return->order_id),
                'date' => $return->created_at?->format('d-m-Y'),
                'status' => (string) (Orders::getReturnStatusses()[$return->status] ?? $return->status),
                '_url' => rescue(fn () => OrderReturnResource::getUrl('view', ['record' => $return->id]), null, false),
            ])->all(),
            'empty' => __('Geen retouren.'),
        ];
    }
}
