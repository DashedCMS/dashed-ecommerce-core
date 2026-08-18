<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';
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
        return __('Omzet statistieken');
    }

    protected function getData(): array
    {
        // De pagina levert de cijfers aan, bij het opbouwen via getWidgetData()
        // en daarna via de gebeurtenis hierboven. Zolang er nog niets is moet
        // dit een lege grafiek zijn en geen fout op een ontbrekende sleutel.
        return $this->graphData['graph'] ?? [
            'datasets' => [],
            'labels' => [],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
