<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Widgets\ChartWidget;

class PaymentMethodChart extends ChartWidget
{
    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected $listeners = [
        'updateGraphData' => 'updateGraphData',
    ];

    public $graphData;

    public function updateGraphData($data): void
    {
        $this->graphData = $data;
    }

    public function getHeading(): string
    {
        return ($this->graphData['step'] ?? 'day') === 'week'
            ? __('Mislukte pogingen per week')
            : __('Mislukte pogingen per dag');
    }

    protected function getData(): array
    {
        return $this->graphData['graph'] ?? ['labels' => [], 'datasets' => []];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
