<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class YearlyRevenueStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Jaarlijkse omzetstatistieken';

    protected function getCards(): array
    {
        $statistics = Cache::remember('yearly-revenue-stats', 60 * 60, function () {
            $statistics = [];

            $yearOrders = Order::where('created_at', '>=', now()->startOfYear())->isPaid()->get();
            $statistics['year'] = [
                'orders' => $yearOrders->count(),
                'products' => OrderProduct::whereIn('order_id', $yearOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
                'orderAmount' => $yearOrders->sum('total'),
            ];
            $statistics['year']['averageOrderAmount'] = $yearOrders->count() ? CurrencyHelper::formatPrice($statistics['year']['orderAmount'] / $statistics['year']['orders']) : CurrencyHelper::formatPrice(0);
            $statistics['year']['orderAmount'] = CurrencyHelper::formatPrice($statistics['year']['orderAmount']);

            $yearReturnOrders = Order::where('created_at', '>=', now()->startOfYear())->isReturn()->get();
            $statistics['yearReturn'] = [
                'orders' => $yearReturnOrders->count(),
                'products' => OrderProduct::whereIn('order_id', $yearReturnOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
                'orderAmount' => $yearReturnOrders->sum('total'),
            ];
            $statistics['yearReturn']['averageOrderAmount'] = $yearReturnOrders->count() ? CurrencyHelper::formatPrice($statistics['yearReturn']['orderAmount'] / $statistics['yearReturn']['orders']) : CurrencyHelper::formatPrice(0);
            $statistics['yearReturn']['orderAmount'] = CurrencyHelper::formatPrice($statistics['yearReturn']['orderAmount']);

            return $statistics;
        });

        return [
            StatsOverviewWidget\Stat::make('Aantal bestellingen (dit jaar)', $statistics['year']['orders'])
                ->description($statistics['yearReturn']['orders'] . ' retour'),
            StatsOverviewWidget\Stat::make('Totaal bedrag', $statistics['year']['orderAmount'])
                ->description($statistics['yearReturn']['orderAmount'] . ' retour'),
            StatsOverviewWidget\Stat::make('Gemiddelde waarde per order', $statistics['year']['averageOrderAmount'])
                ->description($statistics['yearReturn']['averageOrderAmount'] . ' retour'),
            StatsOverviewWidget\Stat::make('Aantal producten verkocht', $statistics['year']['products'])
                ->description($statistics['yearReturn']['products'] . ' retour'),
        ];
    }
}
