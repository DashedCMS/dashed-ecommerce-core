<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;

class PaymentMethodPieChartWidget extends ChartWidget
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

        $formats = Dashboard::getFormatsByStep($steps);
        $startFormat = $formats['startFormat'];
        $endFormat = $formats['endFormat'];

        $data = Cache::remember("payment-pie-chart-data-{$startDate}-{$endDate}-{$steps}", 60 * 60, function () use ($startDate, $endDate, $startFormat, $endFormat) {
            // Eén gegroepeerde telling van de betaalde betalingen in de periode.
            // Eerder laadde deze widget elke betaling van de periode als model en
            // schreef er ook nog een ontbrekend payment_method_id bij: een
            // dashboard hoort te lezen, en de taart groepeert toch op naam.
            $rows = OrderPayment::query()
                ->paid()
                ->whereNotNull('payment_method')
                ->where('created_at', '>=', $startDate->copy()->$startFormat())
                ->where('created_at', '<=', $endDate->copy()->$endFormat())
                ->toBase()
                ->selectRaw('payment_method, COUNT(*) as aantal')
                ->groupBy('payment_method')
                ->orderBy('payment_method')
                ->get();

            $pieData = [];
            $pieColors = [];
            $pieLabels = [];

            foreach ($rows as $row) {
                $pieData[] = (int) $row->aantal;
                $pieLabels[] = $row->payment_method;
                $pieColors[] = '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
            }

            return [
                'pieData' => $pieData,
                'pieColors' => $pieColors,
                'pieLabels' => $pieLabels,
            ];
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
        return 'Gebruikte betaalmethodes';
    }
}
