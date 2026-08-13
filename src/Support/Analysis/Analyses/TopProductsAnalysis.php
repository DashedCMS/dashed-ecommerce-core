<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis\Analyses;

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\OrderLineQuery;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

/**
 * Toppers op omzet en op aantallen, bewust apart. Dat zijn zelden dezelfde
 * producten, en het verschil ertussen is zelf het interessante deel.
 */
class TopProductsAnalysis implements SalesAnalysis
{
    protected const LIMIT = 20;

    /** Bij minder producten dan dit zegt "hoog op stuks, laag op omzet" niets. */
    protected const MINIMUM_PRODUCTS = 4;

    public static function key(): string
    {
        return 'toppers';
    }

    public static function label(): string
    {
        return __('Toppers');
    }

    public static function group(): string
    {
        return 'verkoop';
    }

    public static function isAvailable(AnalysisContext $context): bool
    {
        return true;
    }

    public function run(AnalysisContext $context): AnalysisResult
    {
        $rows = OrderLineQuery::lines($context->period, $context->siteId)
            ->join('dashed__products as p', 'p.id', '=', 'op.product_id')
            ->selectRaw('op.product_id, MAX(op.name) as name, SUM(op.price) as revenue, SUM(op.quantity) as units')
            ->groupBy('op.product_id')
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'revenue' => round((float) $row->revenue, 2),
                'units' => (int) $row->units,
            ])
            ->all();

        $byRevenue = $rows;
        usort($byRevenue, fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']);

        $byUnits = $rows;
        usort($byUnits, fn (array $a, array $b) => $b['units'] <=> $a['units']);

        return new AnalysisResult(
            facts: [
                'by_revenue' => array_slice($byRevenue, 0, self::LIMIT),
                'by_units' => array_slice($byUnits, 0, self::LIMIT),
            ],
            signals: $this->signals($byRevenue, $byUnits),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $byRevenue
     * @param  array<int, array<string, mixed>>  $byUnits
     * @return array<int, Signal>
     */
    protected function signals(array $byRevenue, array $byUnits): array
    {
        $total = count($byRevenue);

        if ($total < self::MINIMUM_PRODUCTS) {
            return [];
        }

        $revenueRank = [];
        foreach ($byRevenue as $position => $row) {
            $revenueRank[$row['product_id']] = $position;
        }

        $signals = [];

        foreach (array_slice($byUnits, 0, 3) as $row) {
            // Bij de top drie op aantallen, maar in de onderste helft op
            // omzet: veel stuks, weinig kassa.
            if (($revenueRank[$row['product_id']] ?? 0) < (int) ceil($total / 2)) {
                continue;
            }

            $signals[] = new Signal(
                severity: Signal::OPPORTUNITY,
                title: __('Veel verkocht, weinig omzet: :product', ['product' => $row['name']]),
                explanation: __('Dit product draagt het volume maar niet de omzet. De moeite waard om naar de prijs of naar bijverkoop te kijken.'),
                numbers: [
                    __('Stuks') => $row['units'],
                    __('Omzet') => $row['revenue'],
                ],
            );
        }

        return $signals;
    }
}
