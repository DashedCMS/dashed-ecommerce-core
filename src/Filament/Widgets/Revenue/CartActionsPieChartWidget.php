<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;
use Dashed\DashedEcommerceCore\Models\EcommerceActionLog;

class CartActionsPieChartWidget extends ChartWidget
{
    protected static ?int $sort = 300;

    protected function getType(): string
    {
        return 'pie';
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

        if ($this->filters['steps'] == 'per_hour') {
            $startFormat = 'startOfDay';
            $endFormat = 'endOfDay';
            $addFormat = 'addHour';
        } elseif ($this->filters['steps'] == 'per_day') {
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

        $data = Cache::remember("add-to-cart-pie-chart-data-{$startDate}-{$endDate}-{$steps}", 60 * 60, function () use ($startDate, $endDate, $startFormat, $endFormat) {
            $pieData = [];
            $pieColors = [];
            $pieLabels = [];

            $addToCartActions = EcommerceActionLog::where('action_type', 'add_to_cart')
                ->where('created_at', '>=', $startDate->$startFormat())->where('created_at', '<=', $endDate->$endFormat())
                ->get();
            $products = Product::whereIn('id', $addToCartActions->pluck('product_id')->unique())->get();
            foreach ($products as $product) {
                $pieLabels[] = $product->name;
                $pieData[] = $addToCartActions->where('product_id', $product->id)->sum('quantity');
                $pieColors[] = '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
            }

            $data = [
                'pieData' => $pieData,
                'pieColors' => $pieColors,
                'pieLabels' => $pieLabels,
            ];

            return $data;
        });

        return [
            'datasets' => [
                [
                    'data' => $data['pieData'],
                    'backgroundColor' => $data['pieColors'],
                ],
            ],
            'labels' => $data['pieLabels'],
        ];
    }

    public function getHeading(): ?string
    {
        return 'Toegevoegde producten in winkelwagentje (30 dagen)';
    }
}
