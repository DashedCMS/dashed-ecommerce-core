<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentMethodCards extends StatsOverviewWidget
{
    protected $listeners = [
        'updateGraphData' => 'updateGraphData',
    ];

    public $graphData;

    public function updateGraphData($data): void
    {
        $this->graphData = $data;
    }

    protected function getStats(): array
    {
        $totals = $this->graphData['totals'] ?? [];

        return [
            Stat::make(__('Betaalpogingen'), (int) ($totals['attempts'] ?? 0)),
            Stat::make(__('Betaald'), (int) ($totals['paid'] ?? 0)),
            Stat::make(__('Mislukt of geannuleerd'), (int) ($totals['failed'] ?? 0)),
            Stat::make(__('Slagingspercentage'), isset($totals['success_rate']) ? number_format($totals['success_rate'], 1, ',', '.') . '%' : '-')
                ->description(__('Betaald van de afgeronde pogingen')),
        ];
    }
}
