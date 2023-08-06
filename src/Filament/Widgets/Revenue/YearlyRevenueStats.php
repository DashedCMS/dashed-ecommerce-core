<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class YearlyRevenueStats extends StatsOverviewWidget
{
    protected static string $view = 'dashed-ecommerce-core::widgets.revenue-stats-widget';

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
            [
                'name' => 'Aantal bestellingen (dit jaar)',
                'number' => $statistics['year']['orders'],
                'retourNumber' => $statistics['yearReturn']['orders'],
            ],
            [
                'name' => 'Totaal bedrag',
                'number' => $statistics['year']['orderAmount'],
                'retourNumber' => $statistics['yearReturn']['orderAmount'],
            ],
            [
                'name' => 'Gemiddelde waarde per order',
                'number' => $statistics['year']['averageOrderAmount'],
                'retourNumber' => $statistics['yearReturn']['averageOrderAmount'],
            ],
            [
                'name' => 'Aantal producten verkocht',
                'number' => $statistics['year']['products'],
                'retourNumber' => $statistics['yearReturn']['products'],
            ],
        ];
    }
}
