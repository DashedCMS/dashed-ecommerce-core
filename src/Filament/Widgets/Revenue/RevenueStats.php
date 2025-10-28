<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;

class RevenueStats extends StatsOverviewWidget
{
    //    protected string $view = 'dashed-ecommerce-core::widgets.revenue-stats-widget';
    public ?array $filters = [];

    protected $listeners = [
        'setPageFiltersData',
    ];

    protected function getHeading(): ?string
    {
        return Dashboard::getPeriodOptions()[$this->filters['period']];
    }

    public function mount(): void
    {
        $this->filters = Dashboard::getStartData();
    }

    public function setPageFiltersData($data)
    {
        $this->filters = $data;
    }

    protected function getCards(): array
    {

        $startDate = $this->filters['startDate'] ? Carbon::parse($this->filters['startDate']) : now()->subMonth();
        $endDate = $this->filters['endDate'] ? Carbon::parse($this->filters['endDate']) : now();
        $steps = $this->filters['steps'] ?? 'per_day';

        if ($this->filters['steps'] == 'per_day') {
            $startFormat = 'startOfDay';
            $endFormat = 'endOfDay';
            $addFormat = 'addDay';
        } elseif ($this->filters['steps'] == 'per_week') {
            $startFormat = 'startOfWeek';
            $endFormat = 'endOfWeek';
            $addFormat = 'addWeek';
        } elseif ($this->filters['steps'] == 'per_month') {
            $startFormat = 'startOfMonth';
            $endFormat = 'endOfMonth';
            $addFormat = 'addMonth';
        }

        $statistics = [];

        $normalOrders = Order::where('created_at', '>=', $startDate->$startFormat())->where('created_at', '<=', $endDate->$endFormat())->isPaid()->get();
        $statistics['normal'] = [
            'orders' => $normalOrders->count(),
            'products' => OrderProduct::whereIn('order_id', $normalOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
            'orderAmount' => $normalOrders->sum('total'),
        ];
        $statistics['normal']['averageOrderAmount'] = $normalOrders->count() ? CurrencyHelper::formatPrice($statistics['normal']['orderAmount'] / $statistics['normal']['orders']) : CurrencyHelper::formatPrice(0);
        $statistics['normal']['orderAmount'] = CurrencyHelper::formatPrice($statistics['normal']['orderAmount']);

        $normalReturnOrders = Order::where('created_at', '>=', $startDate->$startFormat())->where('created_at', '<=', $endDate->$endFormat())->isReturn()->get();
        $statistics['normalReturn'] = [
            'orders' => $normalReturnOrders->count(),
            'products' => OrderProduct::whereIn('order_id', $normalReturnOrders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
            'orderAmount' => $normalReturnOrders->sum('total'),
        ];
        $statistics['normalReturn']['averageOrderAmount'] = $normalReturnOrders->count() ? CurrencyHelper::formatPrice($statistics['normalReturn']['orderAmount'] / $statistics['normalReturn']['orders']) : CurrencyHelper::formatPrice(0);
        $statistics['normalReturn']['orderAmount'] = CurrencyHelper::formatPrice($statistics['normalReturn']['orderAmount']);

        return [
            StatsOverviewWidget\Stat::make('Aantal bestellingen', $statistics['normal']['orders'])
                ->description($statistics['normalReturn']['orders'] . ' retour'),
            StatsOverviewWidget\Stat::make('Totaal bedrag', $statistics['normal']['orderAmount'])
                ->description($statistics['normalReturn']['orderAmount'] . ' retour'),
            StatsOverviewWidget\Stat::make('Gemiddelde waarde per order', $statistics['normal']['averageOrderAmount'])
                ->description($statistics['normalReturn']['averageOrderAmount'] . ' retour'),
            StatsOverviewWidget\Stat::make('Aantal producten verkocht', $statistics['normal']['products'])
                ->description($statistics['normalReturn']['products'] . ' retour'),
        ];
    }
}
