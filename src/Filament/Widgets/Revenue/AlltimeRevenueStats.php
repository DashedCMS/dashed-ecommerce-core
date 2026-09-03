<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;

class AlltimeRevenueStats extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        $statistics = Cache::remember('all-time-revenue-stats', 60 * 60, function () {
            $normal = OrderStatistics::totals(Order::query()->isPaid());
            $return = OrderStatistics::totals(Order::query()->isReturn());

            $average = fn (array $totals) => CurrencyHelper::formatPrice($totals['orders'] ? $totals['amount'] / $totals['orders'] : 0);

            return [
                'allTime' => [
                    'orders' => $normal['orders'],
                    'products' => $normal['products'],
                    'orderAmount' => CurrencyHelper::formatPrice($normal['amount']),
                    'averageOrderAmount' => $average($normal),
                ],
                'allTimeReturn' => [
                    'orders' => $return['orders'],
                    'products' => $return['products'],
                    'orderAmount' => CurrencyHelper::formatPrice($return['amount']),
                    'averageOrderAmount' => $average($return),
                ],
            ];
        });

        return [
            StatsOverviewWidget\Stat::make('Aantal bestellingen (altijd)', $statistics['allTime']['orders'])
                ->description(__(':waarde retour', ['waarde' => $statistics['allTimeReturn']['orders']])),
            StatsOverviewWidget\Stat::make('Totaal bedrag', $statistics['allTime']['orderAmount'])
                ->description(__(':waarde retour', ['waarde' => $statistics['allTimeReturn']['orderAmount']])),
            StatsOverviewWidget\Stat::make('Gemiddelde waarde per order', $statistics['allTime']['averageOrderAmount'])
                ->description(__(':waarde retour', ['waarde' => $statistics['allTimeReturn']['averageOrderAmount']])),
            StatsOverviewWidget\Stat::make('Aantal producten verkocht', $statistics['allTime']['products'])
                ->description(__(':waarde retour', ['waarde' => $statistics['allTimeReturn']['products']])),
        ];
    }

    public static function canView(): bool
    {
        return Order::where('created_at', '<', now()->startOfYear())->exists();
    }
}
