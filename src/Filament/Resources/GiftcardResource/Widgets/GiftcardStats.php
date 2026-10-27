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
 *  - openstaand saldo van verkochte bonnen (discount_amount + reserved_amount),
 *    de schuld aan klanten die een bon gekocht hebben
 */
class GiftcardStats extends StatsOverviewWidget
{
    /**
     * Openstaand saldo van verkochte cadeaubonnen: resterend plus gereserveerd
     * (een lopende checkout houdt een deel apart). Verkochte bonnen verlopen niet.
     */
    public static function openSoldBalance(): float
    {
        return round((float) DiscountCode::soldGiftcard()
            ->selectRaw('COALESCE(SUM(discount_amount + COALESCE(reserved_amount, 0)), 0) as balance')
            ->value('balance'), 2);
    }

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
                ->description(__('Niet-verlopen, restsaldo > €0'))
                ->color('success')
                ->url(\Dashed\DashedCore\Filament\Support\ResourceFilterUrl::for(
                    \Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource::class,
                    ['has_balance' => 1],
                )),
            Stat::make('Waarde nog te besteden', CurrencyHelper::formatPrice($totalRemaining))
                ->description(__('Som van resterend saldo op actieve kaarten'))
                ->color('primary'),
            Stat::make('Openstaand saldo verkochte bonnen', CurrencyHelper::formatPrice(static::openSoldBalance()))
                ->description(__('Nog te besteden op gekochte cadeaubonnen, incl. gereserveerd'))
                ->color('info'),
            Stat::make('Totaal uitgegeven', CurrencyHelper::formatPrice($totalSpent))
                ->description(__('Som van reeds verzilverd bedrag'))
                ->color('warning'),
        ];
    }
}
