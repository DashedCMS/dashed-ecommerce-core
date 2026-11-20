<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Orders;

use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedEcommerceCore\Classes\OrderListTabs;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

/**
 * Work queue under the order list: the same definitions as the tabs above it
 * (OrderListTabs), counted in one aggregate query over all orders, each tile
 * linking to its tab. Deliberately unfiltered: it answers "what is waiting",
 * not "what is in my current selection".
 */
class OrderWorkflowStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = null;

    protected function getHeading(): ?string
    {
        return __('Werkvoorraad');
    }

    protected function getStats(): array
    {
        $counts = OrderListTabs::countFulfillmentTabs(Order::query());

        return [
            Stat::make(__('Te verwerken'), $counts['nieuw'])
                ->description(__('Betaald, niet afgehandeld'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->url($this->tabUrl('nieuw')),
            Stat::make(__('Inpakken'), $counts['inpakken'])
                ->description(__('In behandeling'))
                ->icon('heroicon-o-archive-box')
                ->url($this->tabUrl('inpakken')),
            Stat::make(__('Verzenden'), $counts['verzenden'])
                ->description(__('Ingepakt of klaar om opgehaald te worden'))
                ->icon('heroicon-o-truck')
                ->url($this->tabUrl('verzenden')),
        ];
    }

    protected function tabUrl(string $tab): string
    {
        return OrderResource::getUrl('index', ['tab' => $tab]);
    }
}
