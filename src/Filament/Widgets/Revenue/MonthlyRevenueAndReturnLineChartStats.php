<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Order;

class MonthlyRevenueAndReturnLineChartStats extends ChartWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?string $maxHeight = '300px';
    public function getHeading(): string
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
            'datasets' => [
                [
                    'label' => 'Verkopen',
                    'data' => $statistics['data'],
                    'backgroundColor' => '#196400',
                    'borderColor' => '#196400',
                ],
                [
                    'label' => 'Retouren',
                    'data' => $statistics['returnData'],
                    'backgroundColor' => '#a80000',
                    'borderColor' => '#a80000',
                ],
                [
                    'label' => 'Verkopen + retouren',
                    'data' => $statistics['combinedData'],
                    'backgroundColor' => '#ffbb00',
                    'borderColor' => '#ffbb00',
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
