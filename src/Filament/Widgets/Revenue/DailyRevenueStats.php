<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class DailyRevenueStats extends StatsOverviewWidget
{
    //    protected string $view = 'dashed-ecommerce-core::widgets.revenue-stats-widget';
    protected ?string $heading = 'Dagelijkse omzetstatistieken';

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

        $todayReturnOrders = Order::where('created_at', '>=', now()->startOfDay())->isReturn()->get();
        $statistics['dayReturn'] = [
            'orders' => $todayReturnOrders->count(),
            'products' => OrderProduct::whereIn('order_id', $todayReturnOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
            'orderAmount' => $todayReturnOrders->sum('total'),
        ];
        $statistics['dayReturn']['averageOrderAmount'] = $todayReturnOrders->count() ? CurrencyHelper::formatPrice($statistics['dayReturn']['orderAmount'] / $statistics['dayReturn']['orders']) : CurrencyHelper::formatPrice(0);
        $statistics['dayReturn']['orderAmount'] = CurrencyHelper::formatPrice($statistics['dayReturn']['orderAmount']);

        return [
            StatsOverviewWidget\Stat::make('Aantal bestellingen (vandaag)', $statistics['day']['orders'])
                ->description(__(':waarde retour', ['waarde' => $statistics['dayReturn']['orders']])),
            StatsOverviewWidget\Stat::make('Totaal bedrag', $statistics['day']['orderAmount'])
                ->description(__(':waarde retour', ['waarde' => $statistics['dayReturn']['orderAmount']])),
            StatsOverviewWidget\Stat::make('Gemiddelde waarde per order', $statistics['day']['averageOrderAmount'])
                ->description(__(':waarde retour', ['waarde' => $statistics['dayReturn']['averageOrderAmount']])),
            StatsOverviewWidget\Stat::make('Aantal producten verkocht', $statistics['day']['products'])
                ->description(__(':waarde retour', ['waarde' => $statistics['dayReturn']['products']])),
        ];
    }
}
