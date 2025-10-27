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

        $statistics = Cache::remember("monthly-fun-data-line-chart-stats-{$startDate}-{$endDate}-{$steps}", 60 * 60, function () use ($startDate, $endDate, $startFormat, $endFormat) {
            $statistics = [];

            while ($startDate < $endDate) {
                $statistics['newUsers'][] = User::where('created_at', '>=', $startDate->copy()->$startFormat())->where('created_at', '<=', $startDate->copy()->$endFormat())->count();
                $statistics['newOrders'][] = Order::where('created_at', '>=', $startDate->copy()->$startFormat())->where('created_at', '<=', $startDate->copy()->$endFormat())->isPaid()->count();
                $statistics['labels'][] = $startDate->format('d-m-Y');
                $startDate->addDay();
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
