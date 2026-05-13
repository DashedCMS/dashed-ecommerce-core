<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;

/**
 * Stat-widget bovenaan de productenlijst: telt producten die voorraad
 * gebruiken, op 0 staan en niet "doorverkopen bij geen voorraad" hebben.
 * Klik leidt door naar de out_of_stock-filter.
 */
class ProductOutOfStockStat extends StatsOverviewWidget
{
    protected ?string $heading = null;

    protected function getStats(): array
    {
        $count = Product::query()
            ->where('use_stock', true)
            ->where('stock', '<=', 0)
            ->where('out_of_stock_sellable', false)
            ->count();

        return [
            Stat::make('Producten niet voorradig', (string) $count)
                ->color('danger')
                ->url(ResourceFilterUrl::for(ProductResource::class, [
                    'out_of_stock' => 1,
                ])),
        ];
    }
}
