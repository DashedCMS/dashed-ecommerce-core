<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Qubiqx\QcommerceCore\Classes\Helper;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;

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

            return $statistics;
        });

        return [
            Card::make('Aantal bestellingen (Deze maand)', $statistics['month']['orders']),
            Card::make('Totaal bedrag', $statistics['month']['orderAmount']),
            Card::make('Gemiddelde waarde per order', $statistics['month']['averageOrderAmount']),
            Card::make('Aantal producten verkocht', $statistics['month']['products']),
        ];
    }
}
