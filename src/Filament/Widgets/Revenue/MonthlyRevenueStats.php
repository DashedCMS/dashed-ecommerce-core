<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;
use Qubiqx\QcommerceEcommerceCore\Classes\CurrencyHelper;

class MonthlyRevenueStats extends StatsOverviewWidget
{
    protected static string $view = 'qcommerce-ecommerce-core::widgets.revenue-stats-widget';

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
            [
                'name' => 'Aantal bestellingen (deze maand)',
                'number' => $statistics['month']['orders'],
                'retourNumber' => $statistics['monthReturn']['orders'],
            ],
            [
                'name' => 'Totaal bedrag',
                'number' => $statistics['month']['orderAmount'],
                'retourNumber' => $statistics['monthReturn']['orderAmount'],
            ],
            [
                'name' => 'Gemiddelde waarde per order',
                'number' => $statistics['month']['averageOrderAmount'],
                'retourNumber' => $statistics['monthReturn']['averageOrderAmount'],
            ],
            [
                'name' => 'Aantal producten verkocht',
                'number' => $statistics['month']['products'],
                'retourNumber' => $statistics['monthReturn']['products'],
            ]
        ];
    }
}
