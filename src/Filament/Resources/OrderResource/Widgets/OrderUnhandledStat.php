<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

/**
 * Stat-widget bovenaan de orders-lijst: telt orders die betaald zijn maar
 * nog niet (volledig) zijn afgehandeld. Klik leidt door naar de
 * fulfillment-status-filter "unhandled".
 */
class OrderUnhandledStat extends StatsOverviewWidget
{
    protected ?string $heading = null;

    protected function getStats(): array
    {
        $count = Order::query()
            ->whereIn('status', ['paid', 'partially_paid'])
            ->whereNotIn('fulfillment_status', ['handled', 'partially_handled'])
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
