<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;

class MonthlyRevenueAndReturnLineChartStats extends ChartWidget
{
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
        $startDate = $this->filters['startDate'] ? Carbon::parse($this->filters['startDate']) : now()->subMonth();
        $endDate = $this->filters['endDate'] ? Carbon::parse($this->filters['endDate']) : now();
        $steps = $this->filters['steps'] ?? 'per_day';

        // Eén query voor de hele lijn; een som per stap was er twee per punt,
        // per uur over een jaar ruim zeventienduizend.
        $series = OrderStatistics::perStep($startDate, $endDate, $steps);

        $format = fn (float $value) => number_format($value, 2, '.', '');
        $data = array_map($format, $series['paid']);
        $returnData = array_map($format, $series['return']);
        $combinedData = array_map(fn ($paid, $return) => $format($paid + $return), $series['paid'], $series['return']);

        return [
            'datasets' => [
                [
                    'label' => 'Verkopen',
                    'data' => $data,
                    'backgroundColor' => '#196400',
                    'borderColor' => '#196400',
                ],
                [
                    'label' => 'Retouren',
                    'data' => $returnData,
                    'backgroundColor' => '#a80000',
                    'borderColor' => '#a80000',
                ],
                [
                    'label' => 'Verkopen + retouren',
                    'data' => $combinedData,
                    'backgroundColor' => '#ffbb00',
                    'borderColor' => '#ffbb00',
                ],
            ],
            'labels' => $series['labels'],
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
