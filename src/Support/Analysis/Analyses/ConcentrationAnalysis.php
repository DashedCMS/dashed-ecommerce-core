<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis\Analyses;

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\OrderLineQuery;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

/**
 * Hoe scheef de omzet over het assortiment verdeeld is. Draagt een handvol
 * producten bijna alles, dan is de webshop kwetsbaar voor het uitvallen van
 * precies die producten.
 */
class ConcentrationAnalysis implements SalesAnalysis
{
    /** Onder dit aantal verkochte producten zegt concentratie niets. */
    protected const MINIMUM_PRODUCTS = 5;

    /** Vanaf dit aandeel voor de bovenste vijfde is het een signaal. */
    protected const TOP_SHARE_THRESHOLD = 80.0;

    public static function key(): string
    {
        return 'concentratie';
    }

    public static function label(): string
    {
        return __('Concentratie');
    }

    public static function group(): string
    {
        return 'assortiment';
    }

    public static function isAvailable(AnalysisContext $context): bool
    {
        return true;
    }

    public function run(AnalysisContext $context): AnalysisResult
    {
        $revenues = OrderLineQuery::lines($context->period, $context->siteId)
            ->selectRaw('op.product_id, SUM(op.price) as revenue')
            ->groupBy('op.product_id')
            ->pluck('revenue')
            ->map(fn ($value) => (float) $value)
            ->sortDesc()
            ->values()
            ->all();

        $count = count($revenues);
        $total = array_sum($revenues);

        if ($count === 0 || $total <= 0) {
            return new AnalysisResult(facts: [
                'products_sold' => 0,
                'products_for_half' => 0,
                'top_share_pct' => 0.0,
            ]);
        }

        $forHalf = 0;
        $running = 0.0;
        foreach ($revenues as $revenue) {
            $forHalf++;
            $running += $revenue;
            if ($running >= $total / 2) {
                break;
            }
        }

        $topCount = max(1, (int) ceil($count * 0.2));
        $topShare = round(array_sum(array_slice($revenues, 0, $topCount)) / $total * 100, 1);

        $facts = [
            'products_sold' => $count,
            'products_for_half' => $forHalf,
            'top_share_pct' => $topShare,
        ];

        return new AnalysisResult(facts: $facts, signals: $this->signals($facts));
    }

    /**
     * @param  array<string, int|float>  $facts
     * @return array<int, Signal>
     */
    protected function signals(array $facts): array
    {
        if ($facts['products_sold'] < self::MINIMUM_PRODUCTS) {
            return [];
        }

        if ($facts['top_share_pct'] < self::TOP_SHARE_THRESHOLD) {
            return [];
        }

        return [new Signal(
            severity: Signal::ATTENTION,
            title: __('De omzet leunt op weinig producten'),
            explanation: __('De bovenste vijfde van de verkochte producten maakt :aandeel% van de omzet, en :aantal product(en) dekken al de helft. Valt daar iets van weg, dan raakt dat meteen.', [
                'aandeel' => $facts['top_share_pct'],
                'aantal' => $facts['products_for_half'],
            ]),
            numbers: [
                __('Verkochte producten') => $facts['products_sold'],
                __('Producten voor de helft van de omzet') => $facts['products_for_half'],
            ],
        )];
    }
}
