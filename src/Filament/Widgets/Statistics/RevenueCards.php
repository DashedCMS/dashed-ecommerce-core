<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Widgets\StatsOverviewWidget;

class RevenueCards extends StatsOverviewWidget
{
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

    protected function getStats(): array
    {
        $data = $this->graphData['data'] ?? [];

        if ($data === []) {
            return [];
        }

        return [
            StatsOverviewWidget\Stat::make(__('Aantal bestellingen'), $data['ordersAmount'] ?? 0),
            StatsOverviewWidget\Stat::make(__('Totaal bedrag'), $data['orderAmount'] ?? '-'),
            StatsOverviewWidget\Stat::make(__('Gemiddelde waarde per order'), $data['averageOrderAmount'] ?? '-'),
            StatsOverviewWidget\Stat::make(__('Aantal producten verkocht'), $data['productsSold'] ?? 0),
            StatsOverviewWidget\Stat::make(__('Betalingskosten'), $data['paymentCostsAmount'] ?? '-'),
            StatsOverviewWidget\Stat::make(__('Verzendkosten'), $data['shippingCostsAmount'] ?? '-'),
            StatsOverviewWidget\Stat::make(__('Korting'), $data['discountAmount'] ?? '-'),
            StatsOverviewWidget\Stat::make(__('BTW'), $data['btwAmount'] ?? '-'),
        ];
    }
}
