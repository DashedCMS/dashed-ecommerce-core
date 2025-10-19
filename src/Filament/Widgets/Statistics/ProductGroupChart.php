<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Widgets\ChartWidget;

class ProductGroupChart extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';
    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = '1s';

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
        return 'Product group statistieken';
    }

    protected function getData(): array
    {
        return $this->graphData['graph'];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
