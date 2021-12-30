<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;

class YearlyReturnRevenueStats extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        $statistics = Cache::remember('yearly-return-revenue-stats', 60 * 60, function () {
            $statistics = [];

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
            Card::make('Aantal bestellingen (Dit jaar retour)', $statistics['yearReturn']['orders']),
            Card::make('Totaal bedrag', $statistics['yearReturn']['orderAmount']),
            Card::make('Gemiddelde waarde per order', $statistics['yearReturn']['averageOrderAmount']),
            Card::make('Aantal producten verkocht', $statistics['yearReturn']['products']),
        ];
    }
}
