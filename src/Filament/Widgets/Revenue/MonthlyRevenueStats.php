<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class MonthlyRevenueStats extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        $statistics = Cache::remember('monthly-revenue-stats', 60 * 60, function () {
            $statistics = [];

            $monthOrders = Order::where('created_at', '>=', now()->startOfMonth())->isPaid()->get();
            $statistics['month'] = [
                'orders' => $monthOrders->count(),
                'products' => OrderProduct::whereIn('order_id', $monthOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
                'orderAmount' => $monthOrders->sum('total'),
            ];
            $statistics['month']['averageOrderAmount'] = $monthOrders->count() ? CurrencyHelper::formatPrice($statistics['month']['orderAmount'] / $statistics['month']['orders']) : CurrencyHelper::formatPrice(0);
            $statistics['month']['orderAmount'] = CurrencyHelper::formatPrice($statistics['month']['orderAmount']);

            $monthReturnOrders = Order::where('created_at', '>=', now()->startOfMonth())->isReturn()->get();
            $statistics['monthReturn'] = [
                'orders' => $monthReturnOrders->count(),
                'products' => OrderProduct::whereIn('order_id', $monthReturnOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
                'orderAmount' => $monthReturnOrders->sum('total'),
            ];
            $statistics['monthReturn']['averageOrderAmount'] = $monthReturnOrders->count() ? CurrencyHelper::formatPrice($statistics['monthReturn']['orderAmount'] / $statistics['monthReturn']['orders']) : CurrencyHelper::formatPrice(0);
            $statistics['monthReturn']['orderAmount'] = CurrencyHelper::formatPrice($statistics['monthReturn']['orderAmount']);

            return $statistics;
        });

        return [
            StatsOverviewWidget\Stat::make('Aantal bestellingen (deze maand)', $statistics['month']['orders'])
                ->description($statistics['monthReturn']['orders'] . ' retour'),
            StatsOverviewWidget\Stat::make('Totaal bedrag', $statistics['month']['orderAmount'])
                ->description($statistics['monthReturn']['orderAmount'] . ' retour'),
            StatsOverviewWidget\Stat::make('Gemiddelde waarde per order', $statistics['month']['averageOrderAmount'])
                ->description($statistics['monthReturn']['averageOrderAmount'] . ' retour'),
            StatsOverviewWidget\Stat::make('Aantal producten verkocht', $statistics['month']['products'])
                ->description($statistics['monthReturn']['products'] . ' retour'),
        ];
    }
}
