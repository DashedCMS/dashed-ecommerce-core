<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Widgets\StatsOverviewWidget;

class RevenueCards extends StatsOverviewWidget
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
        return 'Omzet statistieken';
    }

    protected function getCards(): array
    {
        return [
            StatsOverviewWidget\Stat::make('Aantal bestellingen', $this->graphData['data']['ordersAmount']),
            StatsOverviewWidget\Stat::make('Totaal bedrag', $this->graphData['data']['orderAmount']),
            StatsOverviewWidget\Stat::make('Gemiddelde waarde per order', $this->graphData['data']['averageOrderAmount']),
            StatsOverviewWidget\Stat::make('Aantal producten verkocht', $this->graphData['data']['productsSold']),
            StatsOverviewWidget\Stat::make('Betalingskosten', $this->graphData['data']['paymentCostsAmount']),
            StatsOverviewWidget\Stat::make('Verzendkosten', $this->graphData['data']['shippingCostsAmount']),
            StatsOverviewWidget\Stat::make('Korting', $this->graphData['data']['discountAmount']),
            StatsOverviewWidget\Stat::make('BTW', $this->graphData['data']['btwAmount']),
        ];
    }
}
