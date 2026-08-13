<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis\Analyses;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\OrderLineQuery;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\TranslatableColumn;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

/**
 * Binnen een productgroep: welke variant draagt de omzet en welke staan
 * stil. Een groep waarin één kleur alles doet is een aanwijzing dat de rest
 * van het schap kan krimpen, of dat de andere varianten niet gevonden worden.
 */
class VariantSpreadAnalysis implements SalesAnalysis
{
    protected const LIMIT = 25;

    /** Vanaf dit aandeel voor één variant is de groep scheef. */
    protected const DOMINANCE = 85.0;

    public static function key(): string
    {
        return 'varianten';
    }

    public static function label(): string
    {
        return __('Varianten binnen een groep');
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
        // Expliciet gescoopt op de site van de context: een kale query over
        // dashed__products telt anders ook varianten van andere webshops
        // mee, waardoor zowel het aantal varianten als het aandeel van de
        // dominante variant niet meer klopt op een multi-site-installatie.
        $variantCounts = Product::query()
            ->thisSite($context->siteId)
            ->selectRaw('product_group_id, COUNT(*) as variants')
            ->groupBy('product_group_id')
            ->pluck('variants', 'product_group_id');

        $rows = OrderLineQuery::lines($context->period, $context->siteId)
            ->join('dashed__products as p', 'p.id', '=', 'op.product_id')
            ->join('dashed__product_groups as g', 'g.id', '=', 'p.product_group_id')
            ->selectRaw('g.id as group_id, MAX(g.name) as group_name, op.product_id, MAX(op.name) as name, SUM(op.price) as revenue')
            ->groupBy('g.id', 'op.product_id')
            ->get()
            ->groupBy('group_id');

        $groups = [];

        foreach ($rows as $groupId => $variants) {
            $variantCount = (int) ($variantCounts[$groupId] ?? $variants->count());

            if ($variantCount < 2) {
                continue;
            }

            $total = (float) $variants->sum('revenue');

            if ($total <= 0) {
                continue;
            }

            $top = $variants->sortByDesc('revenue')->first();

            $groups[] = [
                'product_group_id' => (int) $groupId,
                'name' => TranslatableColumn::value((string) $top->group_name),
                'variants' => $variantCount,
                'sold_variants' => $variants->count(),
                'dominant_name' => (string) $top->name,
                'dominant_share_pct' => round((float) $top->revenue / $total * 100, 1),
            ];
        }

        usort($groups, fn (array $a, array $b) => $b['dominant_share_pct'] <=> $a['dominant_share_pct']);
        $groups = array_slice($groups, 0, self::LIMIT);

        return new AnalysisResult(
            facts: ['groups' => $groups],
            signals: $this->signals($groups),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, Signal>
     */
    protected function signals(array $groups): array
    {
        $signals = [];

        foreach (array_slice($groups, 0, 5) as $group) {
            if ($group['dominant_share_pct'] < self::DOMINANCE) {
                continue;
            }

            $signals[] = new Signal(
                severity: Signal::OPPORTUNITY,
                title: __('In :groep doet één variant bijna alles', ['groep' => $group['name']]),
                explanation: __(':variant maakt :aandeel% van de omzet van deze groep, terwijl er :aantal varianten zijn. Er verkochten er :verkocht.', [
                    'variant' => $group['dominant_name'],
                    'aandeel' => $group['dominant_share_pct'],
                    'aantal' => $group['variants'],
                    'verkocht' => $group['sold_variants'],
                ]),
                numbers: [
                    __('Varianten') => $group['variants'],
                    __('Verkochte varianten') => $group['sold_variants'],
                ],
            );
        }

        return $signals;
    }
}
