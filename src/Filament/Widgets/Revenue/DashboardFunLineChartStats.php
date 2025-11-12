<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;

class DashboardFunLineChartStats extends ChartWidget
{
    protected static ?int $sort = 300;

    protected int|string|array $columnSpan = 'full';
    protected ?string $maxHeight = '300px';

    public function getHeading(): string
    {
        return 'Fun stats';
    }

    public ?array $filters = [];

    protected $listeners = [
        'setPageFiltersData',
    ];

    public function mount(): void
    {
        $this->filters = Dashboard::getStartData();
    }

    public function setPageFiltersData($data)
    {
        $this->filters = $data;
    }

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ? Carbon::parse($this->filters['startDate']) : now()->subMonth();
        $endDate = $this->filters['endDate'] ? Carbon::parse($this->filters['endDate']) : now();
        $steps = $this->filters['steps'] ?? 'per_day';

        $formats = Dashboard::getFormatsByStep($steps);
        $startFormat = $formats['startFormat'];
        $endFormat = $formats['endFormat'];
        $addFormat = $formats['addFormat'];

        //        $statistics = Cache::remember("monthly-fun-data-line-chart-stats-{$startDate}-{$endDate}-{$steps}-{$addFormat}", 60 * 60, function () use ($startDate, $endDate, $startFormat, $endFormat, $addFormat) {
        $statistics = [];

        while ($startDate < $endDate) {
            $statistics['newUsers'][] = User::where('created_at', '>=', $startDate->copy()->$startFormat())->where('created_at', '<=', $startDate->copy()->$endFormat())->count();
            $statistics['newOrders'][] = Order::where('created_at', '>=', $startDate->copy()->$startFormat())->where('created_at', '<=', $startDate->copy()->$endFormat())->isPaid()->count();
            $statistics['labels'][] = $startDate->format('d-m-Y');
            $startDate->$addFormat();
        }

        //            return $statistics;
        //        });

        return [
            'datasets' => [
                [
                    'label' => 'Nieuwe gebruikers',
                    'data' => $statistics['newUsers'] ?? [0],
                    'backgroundColor' => 'rgba(0, 210, 205, 1)',
                    'borderColor' => 'rgba(0, 210, 205, 1)',
                ],
                [
                    'name' => 'Nieuwe bestellingen',
                    'data' => $statistics['newOrders'] ?? [0],
                    'backgroundColor' => 'rgba(216, 255, 51, 1)',
                    'borderColor' => 'rgba(216, 255, 51, 1)',
                ],
            ],
            'labels' => $statistics['labels'] ?? [],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
