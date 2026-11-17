<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;

/**
 * Stat-widget bovenaan de productenlijst: telt alle producten zonder EAN
 * (NULL of lege string). Klik leidt door naar de without_ean-filter.
 */
class ProductWithoutEanStat extends StatsOverviewWidget
{
    protected ?string $heading = null;

    protected function getStats(): array
    {
        $count = Product::query()->withoutEan()->count();

        return [
            Stat::make('Producten zonder EAN', (string) $count)
                ->color('warning')
                ->url(ResourceFilterUrl::for(ProductResource::class, [
                    'without_ean' => ['isActive' => true],
                ])),
        ];
    }
}
