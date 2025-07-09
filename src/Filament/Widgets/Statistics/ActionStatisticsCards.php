<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Widgets\StatsOverviewWidget;

class ActionStatisticsCards extends StatsOverviewWidget
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
        return 'Actie statistieken';
    }

    protected function getCards(): array
    {
        return [
            StatsOverviewWidget\Stat::make('Totaal added to cart', $this->graphData['data']['totalAddToCarts']),
            StatsOverviewWidget\Stat::make('Totaal remove from cart', $this->graphData['data']['totalRemoveFromCarts']),
            StatsOverviewWidget\Stat::make('Gemiddelde added to cart per dag', $this->graphData['data']['averagePerDayAddToCarts']),
            StatsOverviewWidget\Stat::make('Gemiddelde remove from cart per dag', $this->graphData['data']['averagePerDayRemoveFromCarts']),
            StatsOverviewWidget\Stat::make('Meest added to cart product', $this->graphData['data']['mostAddedProduct']),
            StatsOverviewWidget\Stat::make('Meest removed from cart product', $this->graphData['data']['mostRemovedProduct']),
        ];
    }
}
