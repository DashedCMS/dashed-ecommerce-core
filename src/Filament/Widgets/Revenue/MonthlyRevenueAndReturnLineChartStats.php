<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\Cache;
use Qubiqx\QcommerceEcommerceCore\Models\Order;

class MonthlyRevenueAndReturnLineChartStats extends LineChartWidget
{
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
                $data = Order::where('created_at', '>=', $monthDate->copy()->startOfDay())->where('created_at', '<=', $monthDate->copy()->endOfDay())->isPaid()->sum('total');
                $returnData = Order::where('created_at', '>=', $monthDate->copy()->startOfDay())->where('created_at', '<=', $monthDate->copy()->endOfDay())->isReturn()->sum('total');
                $combinedData = $data + $returnData;
                $statistics['data'][] = $data;
                $statistics['returnData'][] = $returnData;
                $statistics['combinedData'][] = $combinedData;
                $statistics['labels'][] = $monthDate->format('d-m-Y');
                $monthDate->addDay();
            }

            return $statistics;
        });

        return [
            'datasets' => [
                [
                    'label' => 'Verkopen',
                    'data' => $statistics['data'],
                ],
                [
                    'label' => 'Retouren',
                    'data' => $statistics['returnData'],
                ],
                [
                    'label' => 'Verkopen + retouren',
                    'data' => $statistics['combinedData'],
                ],
            ],
            'labels' => $statistics['labels'],
            'chartOptions' => [
                'colors' => [
                    '#0fd912',
                    '#d90f0f',
                    '#F6AD2D',
                ],
            ],
        ];
    }
}
