<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Aurora;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;
use Dashed\DashedEcommerceCore\Filament\Widgets\Aurora\Concerns\UsesDashboardPeriod;

/**
 * Omzetontwikkeling over de gekozen periode, met een wissel tussen omzet en
 * aantal bestellingen. Eén ronde kale rijen via OrderStatistics::perStep(),
 * nooit een query per stap. Bezoekers staan er bewust niet in: daar is geen
 * bron voor.
 */
class RevenueTrendChart extends ChartWidget
{
    use UsesDashboardPeriod {
        mount as mountPeriod;
    }

    protected ?string $maxHeight = '280px';

    public ?string $filter = 'revenue';

    public function mount(): void
    {
        $this->mountPeriod();
        parent::mount();
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Omzetontwikkeling');
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->period()->label;
    }

    protected function getFilters(): ?array
    {
        return [
            'revenue' => __('Omzet'),
            'orders' => __('Bestellingen'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $period = $this->period();
        $data = OrderStatistics::perStep($period->start, $period->end, $period->steps);
        $isOrders = $this->filter === 'orders';

        $format = match ($period->steps) {
            'per_hour' => 'H:i',
            'per_month', 'per_quarter' => 'M Y',
            'per_year' => 'Y',
            default => 'j M',
        };

        return [
            'datasets' => [
                [
                    'label' => $isOrders ? __('Bestellingen') : __('Omzet'),
                    'data' => $isOrders
                        ? $data['paidCount']
                        : array_map(fn ($amount) => round($amount, 2), $data['paid']),
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                ],
            ],
            'labels' => array_map(
                fn (int $timestamp) => Carbon::createFromTimestamp($timestamp, config('app.timezone'))->translatedFormat($format),
                $data['timestamps'],
            ),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
