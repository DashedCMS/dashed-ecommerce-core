<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Aurora;

use Filament\Widgets\Widget;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\OrderStatistics;
use Dashed\DashedEcommerceCore\Filament\Widgets\Aurora\Concerns\UsesDashboardPeriod;

/**
 * Omzet per verkoopkanaal (`order_origin`) in de gekozen periode: een donut
 * in SVG plus een tabel met aandeel en bedrag. Eén GROUP BY-query. Vanaf
 * het vijfde kanaal gaat de rest samen onder "Overig".
 */
class SalesChannelsWidget extends Widget
{
    use UsesDashboardPeriod;

    public const MAX_SLICES = 4;

    /** Vaste toonkleuren; de eerste volgt de hoofdkleur van de site. */
    public const COLORS = ['var(--primary-500, #15D8A5)', '#4F94FF', '#AA79FA', '#F8B957', '#FF737F'];

    protected string $view = 'dashed-ecommerce-core::filament.widgets.aurora.sales-channels';

    protected int|string|array $columnSpan = 'full';

    public static function originLabel(string $origin): string
    {
        return match (strtolower($origin)) {
            'own' => __('Eigen webshop'),
            'pos' => __('Kassa'),
            'bol' => 'Bol.com',
            default => ucfirst($origin),
        };
    }

    /** @return array{total: float, totalFormatted: string, period: ?string, rows: array<int, array{label:string,amount:float,amountFormatted:string,percentage:float,percentageFormatted:string,color:string,offset:float}>} */
    public function getChannels(): array
    {
        $period = $this->period();
        $rows = OrderStatistics::perOrigin($period->start, $period->end);

        if (count($rows) > self::MAX_SLICES + 1) {
            $kept = array_slice($rows, 0, self::MAX_SLICES);
            $rest = array_slice($rows, self::MAX_SLICES);
            $kept[] = [
                'origin' => '__other',
                'orders' => array_sum(array_column($rest, 'orders')),
                'amount' => array_sum(array_column($rest, 'amount')),
            ];
            $rows = $kept;
        }

        $total = array_sum(array_column($rows, 'amount'));
        $offset = 0.0;
        $result = [];

        foreach (array_values($rows) as $index => $row) {
            $percentage = $total > 0 ? $row['amount'] / $total * 100 : 0.0;
            $result[] = [
                'label' => $row['origin'] === '__other' ? __('Overig') : self::originLabel($row['origin']),
                'amount' => $row['amount'],
                'amountFormatted' => CurrencyHelper::formatPrice($row['amount']),
                'percentage' => $percentage,
                'percentageFormatted' => number_format($percentage, 0, ',', '.').'%',
                'color' => self::COLORS[$index % count(self::COLORS)],
                'offset' => $offset,
            ];
            $offset += $percentage;
        }

        return [
            'total' => $total,
            'totalFormatted' => CurrencyHelper::formatPrice($total),
            'period' => $period->label,
            'rows' => $result,
        ];
    }
}
