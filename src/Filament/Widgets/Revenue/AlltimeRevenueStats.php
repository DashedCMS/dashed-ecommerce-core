<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;
use Qubiqx\QcommerceEcommerceCore\Classes\CurrencyHelper;

class AlltimeRevenueStats extends StatsOverviewWidget
{
    protected static string $view = 'qcommerce-ecommerce-core::widgets.revenue-stats-widget';

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
            [
                'name' => 'Aantal bestellingen (altijd)',
                'number' => $statistics['allTime']['orders'],
                'retourNumber' => $statistics['allTimeReturn']['orders'],
            ],
            [
                'name' => 'Totaal bedrag',
                'number' => $statistics['allTime']['orderAmount'],
                'retourNumber' => $statistics['allTimeReturn']['orderAmount'],
            ],
            [
                'name' => 'Gemiddelde waarde per order',
                'number' => $statistics['allTime']['averageOrderAmount'],
                'retourNumber' => $statistics['allTimeReturn']['averageOrderAmount'],
            ],
            [
                'name' => 'Aantal producten verkocht',
                'number' => $statistics['allTime']['products'],
                'retourNumber' => $statistics['allTimeReturn']['products'],
            ],
        ];
    }

    public static function canView(): bool
    {
        return Order::where('created_at', '<', now()->suballTime())->count();
    }
}
