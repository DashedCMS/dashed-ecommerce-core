<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class AlltimeRevenueStats extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        $statistics = Cache::remember('all-time-revenue-stats', 60 * 60, function () {
            $statistics = [];

            $allTimeOrders = Order::isPaid()->get();
            $statistics['allTime'] = [
                'orders' => $allTimeOrders->count(),
                'products' => OrderProduct::whereIn('order_id', $allTimeOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
                'orderAmount' => $allTimeOrders->sum('total'),
            ];
            $statistics['allTime']['averageOrderAmount'] = $allTimeOrders->count() ? CurrencyHelper::formatPrice($statistics['allTime']['orderAmount'] / $statistics['allTime']['orders']) : CurrencyHelper::formatPrice(0);
            $statistics['allTime']['orderAmount'] = CurrencyHelper::formatPrice($statistics['allTime']['orderAmount']);

            $allTimeReturnOrders = Order::isReturn()->get();
            $statistics['allTimeReturn'] = [
                'orders' => $allTimeReturnOrders->count(),
                'products' => OrderProduct::whereIn('order_id', $allTimeReturnOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
                'orderAmount' => $allTimeReturnOrders->sum('total'),
            ];
            $statistics['allTimeReturn']['averageOrderAmount'] = $allTimeReturnOrders->count() ? CurrencyHelper::formatPrice($statistics['allTimeReturn']['orderAmount'] / $statistics['allTimeReturn']['orders']) : CurrencyHelper::formatPrice(0);
            $statistics['allTimeReturn']['orderAmount'] = CurrencyHelper::formatPrice($statistics['allTimeReturn']['orderAmount']);

            return $statistics;
        });

        return [
            StatsOverviewWidget\Stat::make('Aantal bestellingen (altijd)', $statistics['allTime']['orders'])
                ->description($statistics['allTimeReturn']['orders'] . ' retour'),
            StatsOverviewWidget\Stat::make('Totaal bedrag', $statistics['allTime']['orderAmount'])
                ->description($statistics['allTimeReturn']['orderAmount'] . ' retour'),
            StatsOverviewWidget\Stat::make('Gemiddelde waarde per order', $statistics['allTime']['averageOrderAmount'])
                ->description($statistics['allTimeReturn']['averageOrderAmount'] . ' retour'),
            StatsOverviewWidget\Stat::make('Aantal producten verkocht', $statistics['allTime']['products'])
                ->description($statistics['allTimeReturn']['products'] . ' retour'),
        ];
    }

    public static function canView(): bool
    {
        return Order::where('created_at', '<', now()->startOfYear())->count();
    }
}
