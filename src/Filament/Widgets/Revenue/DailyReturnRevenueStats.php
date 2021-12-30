<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Qubiqx\QcommerceCore\Classes\Helper;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;

class DailyReturnRevenueStats extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        $statistics = [];

        $todayReturnOrders = Order::where('created_at', '>=', now()->startOfDay())->isReturn()->get();
        $statistics['dayReturn'] = [
            'orders' => $todayReturnOrders->count(),
            'products' => OrderProduct::whereIn('order_id', $todayReturnOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
            'orderAmount' => $todayReturnOrders->sum('total'),
        ];
        $statistics['dayReturn']['averageOrderAmount'] = $todayReturnOrders->count() ? CurrencyHelper::formatPrice($statistics['dayReturn']['orderAmount'] / $statistics['dayReturn']['orders']) : CurrencyHelper::formatPrice(0);
        $statistics['dayReturn']['orderAmount'] = CurrencyHelper::formatPrice($statistics['dayReturn']['orderAmount']);

        return [
            Card::make('Aantal bestellingen (vandaag retour)', $statistics['dayReturn']['orders']),
            Card::make('Totaal bedrag', $statistics['dayReturn']['orderAmount']),
            Card::make('Gemiddelde waarde per order', $statistics['dayReturn']['averageOrderAmount']),
            Card::make('Aantal producten verkocht', $statistics['dayReturn']['products']),
        ];
    }
}
