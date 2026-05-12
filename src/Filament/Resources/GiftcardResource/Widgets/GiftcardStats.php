<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

/**
 * Stats-widget bovenaan de cadeaukaarten-lijst. Drie KPI's:
 *  - aantal kaarten met restsaldo (discount_amount > 0)
 *  - totale waarde al uitgegeven (sum used_amount)
 *  - totale waarde nog te besteden (sum discount_amount op niet-verlopen
 *    kaarten met saldo)
 */
class GiftcardStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $base = DiscountCode::isGiftcard();

        // We tellen alleen "actieve" cadeaukaarten — kaarten die niet
        // verlopen zijn — voor de restsaldo-stats. Verlopen kaarten met
        // saldo zijn boekhoudkundig "vervallen" en horen niet in het
        // "nog te besteden"-totaal.
        $activeBase = (clone $base)->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', now()->format('Y-m-d H:i:s'));
        });

        $countWithBalance = (clone $activeBase)->where('discount_amount', '>', 0)->count();
        $totalSpent = (float) (clone $base)->sum('used_amount');
        $totalRemaining = (float) (clone $activeBase)->sum('discount_amount');

        return [
            Stat::make('Cadeaukaarten met saldo', $countWithBalance)
                ->description('Niet-verlopen, restsaldo > €0')
                ->color('success')
                ->url(\Dashed\DashedCore\Filament\Support\ResourceFilterUrl::for(
                    \Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource::class,
                    ['has_balance' => 1],
                )),
            Stat::make('Waarde nog te besteden', CurrencyHelper::formatPrice($totalRemaining))
                ->description('Som van resterend saldo op actieve kaarten')
                ->color('primary'),
            Stat::make('Totaal uitgegeven', CurrencyHelper::formatPrice($totalSpent))
                ->description('Som van reeds verzilverd bedrag')
                ->color('warning'),
        ];
    }
}
