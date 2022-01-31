<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\Cache;
use Qubiqx\QcommerceEcommerceCore\Models\Order;

class MonthlyRevenueAndReturnLineChartStats extends LineChartWidget
{
    protected static string $view = 'qcommerce-ecommerce-core::widgets.full-width-chart-widget';

    protected function getHeading(): string
    {
        return 'Verkopen en retouren';
    }

    protected function getData(): array
    {
        $statistics = Cache::remember('monthly-revenue-and-return-line-chart-stats', 60 * 60, function () {
            $statistics = [];

            $monthDate = now()->subMonth();
            while ($monthDate < now()) {
                $data = number_format(Order::where('created_at', '>=', $monthDate->copy()->startOfDay())->where('created_at', '<=', $monthDate->copy()->endOfDay())->isPaid()->sum('total'), 2, '.', '');
                $returnData = number_format(Order::where('created_at', '>=', $monthDate->copy()->startOfDay())->where('created_at', '<=', $monthDate->copy()->endOfDay())->isReturn()->sum('total'), 2, '.', '');
                $combinedData = number_format($data + $returnData, 2, '.', '');
                $statistics['data'][] = $data;
                $statistics['returnData'][] = $returnData;
                $statistics['combinedData'][] = $combinedData;
                $statistics['labels'][] = $monthDate->format('d-m-Y');
                $monthDate->addDay();
            }

            return $statistics;
        });

        return [
            'values' => [
                [
                    'name' => 'Verkopen',
                    'data' => $statistics['data'],
                ],
                [
                    'name' => 'Retouren',
                    'data' => $statistics['returnData'],
                ],
                [
                    'name' => 'Verkopen + retouren',
                    'data' => $statistics['combinedData'],
                ],
            ],
            'colors' => [
                '#196400',
                '#a80000',
                'rgba(250, 255, 0, 0.5)'
            ],
            'labels' => $statistics['labels'],
        ];
    }
}
