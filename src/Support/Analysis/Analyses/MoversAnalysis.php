<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis\Analyses;

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\OrderLineQuery;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

/**
 * Wie steeg en wie zakte, tegen de vorige periode. Vorig jaar staat er
 * altijd bij: een daling die vorig jaar ook optrad is seizoen, geen
 * probleem.
 */
class MoversAnalysis implements SalesAnalysis
{
    protected const LIMIT = 20;

    /** Verandering vanaf dit percentage telt als beweging. */
    protected const CHANGE = 30.0;

    /** Onder dit bedrag in beide periodes zegt een percentage niets. */
    protected const MINIMUM_REVENUE = 50.0;

    public static function key(): string
    {
        return 'stijgers-en-dalers';
    }

    public static function label(): string
    {
        return __('Stijgers en dalers');
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
        $current = $this->revenuePerProduct($context->period, $context->siteId);
        $previous = $this->revenuePerProduct($context->previous, $context->siteId);
        $lastYear = $this->revenuePerProduct($context->lastYear, $context->siteId);

        $risers = [];
        $fallers = [];

        foreach ($current as $productId => $row) {
            $previousRevenue = $previous[$productId]['revenue'] ?? 0.0;
            $lastYearRevenue = $lastYear[$productId]['revenue'] ?? 0.0;

            // Te klein aan beide kanten: geen beweging om over te praten.
            if ($row['revenue'] < self::MINIMUM_REVENUE && $previousRevenue < self::MINIMUM_REVENUE) {
                continue;
            }

            $change = $previousRevenue > 0
                ? round(($row['revenue'] - $previousRevenue) / $previousRevenue * 100, 1)
                : null;

            $entry = [
                'product_id' => $productId,
                'name' => $row['name'],
                'revenue' => $row['revenue'],
                'previous_revenue' => $previousRevenue,
                'last_year_revenue' => $lastYearRevenue,
                'change_pct' => $change,
            ];

            // change_pct null betekent: vorige periode niets verkocht. Dat is
            // een stijger, maar zonder percentage; oneindig zou de sortering
            // en de tekst onbruikbaar maken.
            if ($change === null || $change >= self::CHANGE) {
                $risers[] = $entry;
            } elseif ($change <= -self::CHANGE) {
                $fallers[] = $entry;
            }
        }

        usort($risers, fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']);
        usort($fallers, fn (array $a, array $b) => $a['change_pct'] <=> $b['change_pct']);

        $risers = array_slice($risers, 0, self::LIMIT);
        $fallers = array_slice($fallers, 0, self::LIMIT);

        return new AnalysisResult(
            facts: ['risers' => $risers, 'fallers' => $fallers],
            signals: $this->signals($fallers),
        );
    }

    /** @return array<int, array{name: string, revenue: float}> */
    protected function revenuePerProduct(AnalysisPeriod $period, string $siteId): array
    {
        return OrderLineQuery::lines($period, $siteId)
            ->selectRaw('op.product_id, MAX(op.name) as name, SUM(op.price) as revenue')
            ->groupBy('op.product_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->product_id => [
                    'name' => (string) $row->name,
                    'revenue' => round((float) $row->revenue, 2),
                ],
            ])
            ->all();
    }

    /**
     * Alleen dalers melden die ook tegen vorig jaar zakken. Zakt hij alleen
     * tegen de vorige periode, dan is het waarschijnlijk seizoen.
     *
     * @param  array<int, array<string, mixed>>  $fallers
     * @return array<int, Signal>
     */
    protected function signals(array $fallers): array
    {
        $signals = [];

        foreach (array_slice($fallers, 0, 5) as $faller) {
            if ($faller['last_year_revenue'] > 0 && $faller['revenue'] >= $faller['last_year_revenue']) {
                continue;
            }

            $signals[] = new Signal(
                severity: Signal::ATTENTION,
                title: __(':product zakte :procent%', [
                    'product' => $faller['name'],
                    'procent' => abs((float) $faller['change_pct']),
                ]),
                explanation: __('Deze daling zie je ook terug tegenover vorig jaar, dus het is waarschijnlijk geen seizoenseffect.'),
                numbers: [
                    __('Deze periode') => $faller['revenue'],
                    __('Vorige periode') => $faller['previous_revenue'],
                    __('Vorig jaar') => $faller['last_year_revenue'],
                ],
            );
        }

        return $signals;
    }
}
