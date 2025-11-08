<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Widgets\Concerns\InteractsWithfilters;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;

class MonthlyRevenueAndReturnLineChartStats extends ChartWidget
{
    //    use InteractsWithfilters;

    protected int|string|array $columnSpan = 'full';
    protected ?string $maxHeight = '300px';
    public ?array $filters = [];

    protected $listeners = [
        'setPageFiltersData',
    ];

    public function mount(): void
    {
        $this->filters = Dashboard::getStartData();
    }

    public function getHeading(): string
    {
        return 'Verkopen en retouren';
    }

    protected function getData(): array
    {
        //        $statistics = Cache::remember('monthly-revenue-and-return-line-chart-stats', 60 * 60, function () {
        $statistics = [];

        $startDate = $this->filters['startDate'] ? Carbon::parse($this->filters['startDate']) : now()->subMonth();
        $endDate = $this->filters['endDate'] ? Carbon::parse($this->filters['endDate']) : now();

        $steps = $this->filters['steps'] ?? 'per_day';
        $formats = Dashboard::getFormatsByStep($steps);
        $startFormat = $formats['startFormat'];
        $endFormat = $formats['endFormat'];
        $addFormat = $formats['addFormat'];

        while ($startDate < $endDate) {
            $data = number_format(Order::where('created_at', '>=', $startDate->copy()->$startFormat())->where('created_at', '<=', $startDate->copy()->$endFormat())->isPaid()->sum('total'), 2, '.', '');
            $returnData = number_format(Order::where('created_at', '>=', $startDate->copy()->$startFormat())->where('created_at', '<=', $startDate->copy()->$endFormat())->isReturn()->sum('total'), 2, '.', '');
            $combinedData = number_format($data + $returnData, 2, '.', '');
            $statistics['data'][] = $data;
            $statistics['returnData'][] = $returnData;
            $statistics['combinedData'][] = $combinedData;
            if ($this->filters['steps'] == 'per_hour') {
                $statistics['labels'][] = $startDate->format('d-m-Y H:i');
            } else {
                $statistics['labels'][] = $startDate->format('d-m-Y');
            }
            $startDate->$addFormat();
        }

        //            return $statistics;
        //        });

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

    public function setPageFiltersData($data)
    {
        $this->filters = $data;
    }
}
