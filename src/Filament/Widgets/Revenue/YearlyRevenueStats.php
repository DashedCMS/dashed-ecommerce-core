<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;

class YearlyRevenueStats extends StatsOverviewWidget
{
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

            return $statistics;
        });

        return [
            Card::make('Aantal bestellingen (Dit jaar)', $statistics['year']['orders']),
            Card::make('Totaal bedrag', $statistics['year']['orderAmount']),
            Card::make('Gemiddelde waarde per order', $statistics['year']['averageOrderAmount']),
            Card::make('Aantal producten verkocht', $statistics['year']['products']),
        ];
    }
}
