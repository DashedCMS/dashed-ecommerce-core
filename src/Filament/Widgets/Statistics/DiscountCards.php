<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Widgets\StatsOverviewWidget;

class DiscountCards extends StatsOverviewWidget
{
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
        return 'Korting statistieken';
    }

    protected function getCards(): array
    {
        return [
            StatsOverviewWidget\Stat::make('Korting', $this->graphData['data']['discountAmount']),
            StatsOverviewWidget\Stat::make('Aantal bestellingen', $this->graphData['data']['ordersAmount']),
            StatsOverviewWidget\Stat::make('Totaal bedrag', $this->graphData['data']['orderAmount']),
            StatsOverviewWidget\Stat::make('Gemiddelde korting per order', $this->graphData['data']['averageDiscountAmount']),
            StatsOverviewWidget\Stat::make('Gemiddelde waarde per order', $this->graphData['data']['averageOrderAmount']),
            StatsOverviewWidget\Stat::make('Aantal producten verkocht', $this->graphData['data']['productsSold']),
        ];
    }
}
