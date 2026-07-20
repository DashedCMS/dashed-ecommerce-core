<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

/**
 * Stat-widget bovenaan de orders-lijst: telt betaalde orders die op
 * fulfillment-status 'unhandled' (niet afgehandeld) staan. Orders die al in
 * behandeling/ingepakt/verzonden zijn tellen niet mee. Klik leidt door naar
 * de fulfillment-status-filter "unhandled".
 */
class OrderUnhandledStat extends StatsOverviewWidget
{
    protected ?string $heading = null;

    protected function getStats(): array
    {
        $count = Order::query()
            ->whereIn('status', ['paid', 'partially_paid'])
            ->where('fulfillment_status', 'unhandled')
            ->count();

        return [
            Stat::make('Onafgehandelde orders', (string) $count)
                ->color('warning')
                ->url(ResourceFilterUrl::for(OrderResource::class, [
                    'fulfillment_status' => 'unhandled',
                ])),
        ];
    }
}
