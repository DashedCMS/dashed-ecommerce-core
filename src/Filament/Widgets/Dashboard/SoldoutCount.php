<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Dashboard;

use Carbon\Carbon;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Pages\Dashboard\Dashboard;

class SoldoutCount extends StatsOverviewWidget
{

    protected static ?int $sort = 5;
    protected function getHeading(): ?string
    {
        return 'Voorraad';
    }

    protected function getCards(): array
    {
        $soldOutCount = Product::where('total_stock', '<=', 0)->count();
        $almostSoldOutCount = Product::where('total_stock', '<=', 5)->count();

        return [
            StatsOverviewWidget\Stat::make('Uitverkochte producten', $soldOutCount)
                ->description('Aantal producten die uitverkocht zijn'),
            StatsOverviewWidget\Stat::make('Bijna uitverkochte producten', $almostSoldOutCount)
                ->description('Deze producten hebben minder dan 5 voorraad'),
        ];
    }
}
