<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Aurora;

use Filament\Widgets\Widget;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Classes\Dashboard\DashboardPeriod;
use Dashed\DashedEcommerceCore\Filament\Widgets\Aurora\Concerns\UsesDashboardPeriod;

/**
 * Vier KPI's bovenaan het dashboard: omzet, bestellingen en gemiddelde
 * orderwaarde in de gekozen periode met de delta tegen de periode ervoor,
 * en het aantal betaalde orders dat nog niet is afgehandeld (nu, los van de
 * periode). Twee queries in totaal.
 */
class KpiRowWidget extends Widget
{
    use UsesDashboardPeriod;

    protected string $view = 'dashed-ecommerce-core::filament.widgets.aurora.kpi-row';

    protected int|string|array $columnSpan = 'full';

    /** @return array<int, array{label:string,value:string,delta:?string,deltaPositive:?bool,sub:?string,tone:string,icon:string,url:?string}> */
    public function getKpis(): array
    {
        $period = $this->period();
        $compare = OrderStatistics::comparePeriods($period->start, $period->end, $period->previousStart, $period->previousEnd);
        $current = $compare['current'];
        $previous = $compare['previous'];

        $average = fn (array $totals) => $totals['orders'] ? $totals['amount'] / $totals['orders'] : 0.0;

        $unhandled = Order::query()
            ->whereIn('status', ['paid', 'partially_paid'])
            ->where('fulfillment_status', 'unhandled')
            ->count();

        $sub = __('t.o.v. vorige periode');

        return [
            $this->kpi(__('Omzet'), CurrencyHelper::formatPrice($current['amount']), DashboardPeriod::delta($current['amount'], $previous['amount']), $sub, 'mint', 'heroicon-o-wallet'),
            $this->kpi(__('Bestellingen'), (string) $current['orders'], DashboardPeriod::delta($current['orders'], $previous['orders']), $sub, 'blue', 'heroicon-o-shopping-bag'),
            $this->kpi(__('Gemiddelde orderwaarde'), CurrencyHelper::formatPrice($average($current)), DashboardPeriod::delta($average($current), $average($previous)), $sub, 'purple', 'heroicon-o-receipt-percent'),
            [
                'label' => __('Te verwerken'),
                'value' => (string) $unhandled,
                'delta' => null,
                'deltaPositive' => null,
                'sub' => __('Betaald, nog niet afgehandeld'),
                'tone' => 'amber',
                'icon' => 'heroicon-o-inbox-stack',
                'url' => ResourceFilterUrl::for(OrderResource::class, ['fulfillment_status' => 'unhandled']),
            ],
        ];
    }

    protected function kpi(string $label, string $value, ?float $delta, string $sub, string $tone, string $icon): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'delta' => DashboardPeriod::formatDelta($delta),
            'deltaPositive' => $delta === null ? null : $delta >= 0,
            'sub' => $delta === null ? __('Geen vergelijking met vorige periode') : $sub,
            'tone' => $tone,
            'icon' => $icon,
            'url' => null,
        ];
    }
}
