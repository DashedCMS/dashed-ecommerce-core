<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Dashboard;

use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class CartStatistics extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected function getHeading(): ?string
    {
        return 'Winkelwagen statistieken';
    }

    protected function getCards(): array
    {
        $activeCarts = Cart::query()->whereHas('items')->count();

        // Eén som in de database; de winkelwagentabel groeit met elke bezoeker
        // en alle regels als modellen inladen om ze op te tellen is precies wat
        // het dashboard traag en zwaar maakte.
        $totals = CartItem::query()
            ->whereHas('cart')
            ->toBase()
            ->selectRaw('COALESCE(SUM(quantity), 0) as quantity, COALESCE(SUM(unit_price * quantity), 0) as value')
            ->first();

        return [
            StatsOverviewWidget\Stat::make('Aantal actieve winkelwagens', $activeCarts),
            StatsOverviewWidget\Stat::make('Aantal producten in winkelwagens', (int) ($totals->quantity ?? 0)),
            StatsOverviewWidget\Stat::make('Waarde in winkelwagens', CurrencyHelper::formatPrice((float) ($totals->value ?? 0))),
        ];
    }
}
