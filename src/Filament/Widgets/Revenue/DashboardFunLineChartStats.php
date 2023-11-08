<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Filament\Widgets\ChartWidget;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Order;

class DashboardFunLineChartStats extends ChartWidget
{
    public function getHeading(): string
    {
        return 'Fun stats';
    }

    protected function getData(): array
    {
        $statistics = Cache::remember('monthly-fun-data-line-chart-stats', 60 * 60, function () {
            $statistics = [];

            $monthDate = now()->subMonth();
            while ($monthDate < now()) {
                $statistics['newUsers'][] = User::where('created_at', '>=', $monthDate->copy()->startOfDay())->where('created_at', '<=', $monthDate->copy()->endOfDay())->count();
                $statistics['newOrders'][] = Order::where('created_at', '>=', $monthDate->copy()->startOfDay())->where('created_at', '<=', $monthDate->copy()->endOfDay())->isPaid()->count();
                $statistics['labels'][] = $monthDate->format('d-m-Y');
                $monthDate->addDay();
            }

            return $statistics;
        });

        return [
            'datasets' => [
                [
                    'label' => 'Nieuwe gebruikers',
                    'data' => $statistics['newUsers'],
                    'backgroundColor' => 'rgba(0, 210, 205, 1)',
                    'borderColor' => 'rgba(0, 210, 205, 1)',
                ],
                [
                    'name' => 'Nieuwe bestellingen',
                    'data' => $statistics['newOrders'],
                    'backgroundColor' => 'rgba(216, 255, 51, 1)',
                    'borderColor' => 'rgba(216, 255, 51, 1)',
                ],
            ],
            'labels' => $statistics['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
