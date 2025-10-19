<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Filament\Pages\Dashboard\Dashboard;

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

        $data = Cache::remember("payment-pie-chart-data-{$startDate}-{$endDate}-{$steps}", 60 * 60, function () use ($startDate, $endDate, $startFormat, $endFormat) {
            $paymentMethods = OrderPayment::whereNotNull('payment_method')->distinct('payment_method')->pluck('payment_method');
            $orderPayments = OrderPayment::where('created_at', '>=', $startDate->$startFormat())->where('created_at', '<=', $endDate->$endFormat())->get();
            foreach ($orderPayments as $orderPayment) {
                if (! $orderPayment->payment_method_id) {
                    $correctPaymentMethod = PaymentMethod::where('psp', $orderPayment->psp)->where('name', 'LIKE', '%' . $orderPayment->payment_method . '%')->first();
                    if ($correctPaymentMethod) {
                        $orderPayment->payment_method_id = $correctPaymentMethod->id;
                        $orderPayment->save();
                    }
                }
            }

            $pieData = [];
            $pieColors = [];
            $pieLabels = [];

            foreach ($paymentMethods as $paymentMethod) {
                $pieData[] = OrderPayment::paid()->where('payment_method', $paymentMethod)->count();
                $pieLabels[] = $paymentMethod;
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
        return 'Gebruikte betaalmethodes';
    }
}
