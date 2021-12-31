<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;
use Qubiqx\QcommerceEcommerceCore\Classes\CurrencyHelper;

class MonthlyReturnRevenueStats extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        $statistics = [];

        $statistics = Cache::remember('monthly-return-revenue-stats', 60 * 60, function () {
            $statistics = [];

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
            Card::make('Aantal bestellingen (Deze maand retour)', $statistics['monthReturn']['orders']),
            Card::make('Totaal bedrag', $statistics['monthReturn']['orderAmount']),
            Card::make('Gemiddelde waarde per order', $statistics['monthReturn']['averageOrderAmount']),
            Card::make('Aantal producten verkocht', $statistics['monthReturn']['products']),
        ];
    }
}
