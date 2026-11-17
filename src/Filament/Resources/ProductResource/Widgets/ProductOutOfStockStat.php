<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;

/**
 * Stat-widget bovenaan de productenlijst met twee tellers:
 * - producten die voorraad gebruiken, op 0 staan en niet "doorverkopen bij
 *   geen voorraad" hebben (klik: out_of_stock-filter);
 * - producten zonder EAN, NULL of lege string (klik: without_ean-filter).
 *
 * Bewust één widget: met een tweede los (lazy) stat-widget in de header liep
 * de productenlijst in de browser vast en laadde de tabel nooit.
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
            Stat::make('Producten zonder EAN', (string) Product::query()->withoutEan()->count())
                ->color('warning')
                ->url(ResourceFilterUrl::for(ProductResource::class, [
                    'without_ean' => ['isActive' => true],
                ])),
        ];
    }
}
