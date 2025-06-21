<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Widgets\StatsOverviewWidget;

class ProductGroupCards extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '1s';

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
        return 'Omzet statistieken';
    }

    protected function getCards(): array
    {
        return [
            StatsOverviewWidget\Stat::make('Aantal producten verkocht', $this->graphData['data']['totalQuantitySold']),
            StatsOverviewWidget\Stat::make('Totaal bedrag', $this->graphData['data']['totalAmountSold']),
            StatsOverviewWidget\Stat::make('Gemiddelde kosten per product', $this->graphData['data']['averageCostPerProduct']),
        ];
    }
}
