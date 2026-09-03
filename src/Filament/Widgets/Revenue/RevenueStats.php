<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;

class RevenueStats extends StatsOverviewWidget
{
    public ?array $filters = [];

    protected $listeners = [
        'setPageFiltersData',
    ];

    protected function getHeading(): ?string
    {
        return Dashboard::getPeriodOptions()[$this->filters['period'] ?? 'month'];
    }

    public function mount(): void
    {
        $this->filters = Dashboard::getStartData();
    }

    public function setPageFiltersData($data)
    {
        $this->filters = $data;
    }

    protected function getCards(): array
    {
        $startDate = $this->filters['startDate'] ? Carbon::parse($this->filters['startDate']) : now()->subMonth();
        $endDate = $this->filters['endDate'] ? Carbon::parse($this->filters['endDate']) : now();
        $steps = $this->filters['steps'] ?? 'per_day';

        $formats = Dashboard::getFormatsByStep($steps);
        $startFormat = $formats['startFormat'];
        $endFormat = $formats['endFormat'];

        $inPeriod = fn () => Order::query()
            ->where('created_at', '>=', $startDate->copy()->$startFormat())
            ->where('created_at', '<=', $endDate->copy()->$endFormat());

        $normal = OrderStatistics::totals($inPeriod()->isPaid());
        $return = OrderStatistics::totals($inPeriod()->isReturn());

        $average = fn (array $totals) => CurrencyHelper::formatPrice($totals['orders'] ? $totals['amount'] / $totals['orders'] : 0);

        return [
            StatsOverviewWidget\Stat::make('Aantal bestellingen', $normal['orders'])
                ->description(__(':waarde retour', ['waarde' => $return['orders']])),
            StatsOverviewWidget\Stat::make('Totaal bedrag', CurrencyHelper::formatPrice($normal['amount']))
                ->description(__(':waarde retour', ['waarde' => CurrencyHelper::formatPrice($return['amount'])])),
            StatsOverviewWidget\Stat::make('Gemiddelde waarde per order', $average($normal))
                ->description(__(':waarde retour', ['waarde' => $average($return)])),
            StatsOverviewWidget\Stat::make('Aantal producten verkocht', $normal['products'])
                ->description(__(':waarde retour', ['waarde' => $return['products']])),
        ];
    }
}
