<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;
use Qubiqx\QcommerceEcommerceCore\Classes\CurrencyHelper;

class DailyRevenueStats extends StatsOverviewWidget
{
    protected function getCards(): array
    {
        $statistics = [];

        $todayOrders = Order::where('created_at', '>=', now()->startOfDay())->isPaid()->get();
        $statistics['day'] = [
            'orders' => $todayOrders->count(),
            'products' => OrderProduct::whereIn('order_id', $todayOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
            'orderAmount' => $todayOrders->sum('total'),
        ];
        $statistics['day']['averageOrderAmount'] = $todayOrders->count() ? CurrencyHelper::formatPrice($statistics['day']['orderAmount'] / $statistics['day']['orders']) : CurrencyHelper::formatPrice(0);
        $statistics['day']['orderAmount'] = CurrencyHelper::formatPrice($statistics['day']['orderAmount']);

//                ->description('32k increase')
//                ->descriptionIcon('heroicon-s-trending-up')
//                ->chart([7, 2, 10, 3, 15, 4, 17])
//                ->color('success'),

        return [
            Card::make('Aantal bestellingen (vandaag)', $statistics['day']['orders']),
            Card::make('Totaal bedrag', $statistics['day']['orderAmount']),
            Card::make('Gemiddelde waarde per order', $statistics['day']['averageOrderAmount']),
            Card::make('Aantal producten verkocht', $statistics['day']['products']),
        ];
    }
}
