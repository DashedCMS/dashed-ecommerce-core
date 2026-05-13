<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\CartResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Cart;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedEcommerceCore\Filament\Resources\CartResource;

/**
 * Stat-widget bovenaan de winkelwagen-lijst: telt actieve winkelmandjes.
 * "Actief" = nog niet leeg (heeft items). Klik leidt door naar de
 * is_active-filter.
 */
class CartActiveStat extends StatsOverviewWidget
{
    protected ?string $heading = null;

    protected function getStats(): array
    {
        $count = Cart::query()
            ->whereHas('items')
            ->count();

        return [
            Stat::make('Actieve winkelmandjes', (string) $count)
                ->color('primary')
                ->url(ResourceFilterUrl::for(CartResource::class, [
                    'is_active' => 1,
                ])),
        ];
    }
}
