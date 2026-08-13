<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis\Analyses;

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\OrderLineQuery;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

/**
 * De kale cijfers van de periode, naast de vorige periode en vorig jaar.
 */
class KeyFiguresAnalysis implements SalesAnalysis
{
    /** Vanaf hoeveel procent afwijking is het een signaal. */
    protected const DEVIATION = 25.0;

    /** Onder dit bedrag zegt een procentueel verschil niets zinnigs. */
    protected const MINIMUM_REVENUE = 100.0;

    public static function key(): string
    {
        return 'kerncijfers';
    }

    public static function label(): string
    {
        return __('Kerncijfers');
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
        $current = $this->figures($context->period, $context->siteId);
        $previous = $this->figures($context->previous, $context->siteId);
        $lastYear = $this->figures($context->lastYear, $context->siteId);

        return new AnalysisResult(
            facts: [
                'current' => $current,
                'previous' => $previous,
                'last_year' => $lastYear,
            ],
            signals: $this->signals($current, $previous, $lastYear),
        );
    }

    /** @return array<string, float|int> */
    protected function figures(AnalysisPeriod $period, string $siteId): array
    {
        $orders = OrderLineQuery::orders($period, $siteId)->get(['id', 'total']);
        $revenue = round((float) $orders->sum('total'), 2);
        $orderCount = $orders->count();

        $lines = OrderLineQuery::lines($period, $siteId)
            ->selectRaw('COALESCE(SUM(op.quantity), 0) as units, COUNT(DISTINCT op.product_id) as unique_products')
            ->first();

        return [
            'revenue' => $revenue,
            'orders' => $orderCount,
            'average_order_value' => $orderCount > 0 ? round($revenue / $orderCount, 2) : 0.0,
            'units' => (int) ($lines->units ?? 0),
            'unique_products' => (int) ($lines->unique_products ?? 0),
        ];
    }

    /**
     * Alleen melden wanneer de omzet tegen BEIDE vergelijkingen dezelfde kant
     * op afwijkt. Wijkt hij alleen van de vorige periode af, dan is het
     * waarschijnlijk seizoen; wijkt hij alleen van vorig jaar af, dan is het
     * groei of krimp over het jaar en geen nieuws over deze maand.
     *
     * @param  array<string, float|int>  $current
     * @param  array<string, float|int>  $previous
     * @param  array<string, float|int>  $lastYear
     * @return array<int, Signal>
     */
    protected function signals(array $current, array $previous, array $lastYear): array
    {
        $versusPrevious = $this->deviation($current['revenue'], $previous['revenue']);
        $versusLastYear = $this->deviation($current['revenue'], $lastYear['revenue']);

        if ($versusPrevious === null || $versusLastYear === null) {
            return [];
        }

        $down = $versusPrevious <= -self::DEVIATION && $versusLastYear <= -self::DEVIATION;
        $up = $versusPrevious >= self::DEVIATION && $versusLastYear >= self::DEVIATION;

        if (! $down && ! $up) {
            return [];
        }

        return [new Signal(
            severity: $down ? Signal::ATTENTION : Signal::OPPORTUNITY,
            title: $down ? __('Omzet lager dan beide vergelijkingen') : __('Omzet hoger dan beide vergelijkingen'),
            explanation: $down
                ? __('De omzet ligt zowel onder de vorige periode als onder vorig jaar, dus dit is geen seizoenseffect.')
                : __('De omzet ligt zowel boven de vorige periode als boven vorig jaar.'),
            numbers: [
                __('Deze periode') => $current['revenue'],
                __('Vorige periode') => $previous['revenue'],
                __('Vorig jaar') => $lastYear['revenue'],
            ],
        )];
    }

    /**
     * Procentuele afwijking, of null wanneer de basis te klein is om er iets
     * over te zeggen. Zonder die ondergrens wordt 5 euro tegen 1 euro een
     * stijging van 400 procent.
     */
    protected function deviation(float $current, float $base): ?float
    {
        if ($base < self::MINIMUM_REVENUE) {
            return null;
        }

        return ($current - $base) / $base * 100;
    }
}
