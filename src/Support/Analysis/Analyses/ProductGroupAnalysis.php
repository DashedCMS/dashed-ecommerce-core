<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis\Analyses;

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\OrderLineQuery;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\TranslatableColumn;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

/**
 * Verdeling van de omzet over productgroepen, en vooral: hoe die verdeling
 * verschoof. Een groep die aandeel verliest terwijl de totale omzet gelijk
 * blijft is iets wat je in absolute cijfers niet ziet.
 */
class ProductGroupAnalysis implements SalesAnalysis
{
    /** Verschuiving in aandeel, in procentpunten, vanaf wanneer het een signaal is. */
    protected const SHARE_SHIFT = 15.0;

    public static function key(): string
    {
        return 'groepen';
    }

    public static function label(): string
    {
        return __('Productgroepen');
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
        $current = $this->shares($context->period, $context->siteId);
        $previous = $this->shares($context->previous, $context->siteId);

        $groups = [];

        foreach ($current as $groupId => $row) {
            $groups[] = [
                'product_group_id' => $groupId,
                'name' => $row['name'],
                'revenue' => $row['revenue'],
                'units' => $row['units'],
                'share_pct' => $row['share_pct'],
                'previous_share_pct' => $previous[$groupId]['share_pct'] ?? 0.0,
            ];
        }

        usort($groups, fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']);

        return new AnalysisResult(
            facts: ['groups' => $groups],
            signals: $this->signals($groups, $previous !== []),
        );
    }

    /** @return array<int, array{name: string, revenue: float, units: int, share_pct: float}> */
    protected function shares(AnalysisPeriod $period, string $siteId): array
    {
        $rows = OrderLineQuery::lines($period, $siteId)
            ->join('dashed__products as p', 'p.id', '=', 'op.product_id')
            ->join('dashed__product_groups as g', 'g.id', '=', 'p.product_group_id')
            ->selectRaw('g.id as group_id, MAX(g.name) as name, SUM(op.price) as revenue, SUM(op.quantity) as units')
            ->groupBy('g.id')
            ->get();

        $total = (float) $rows->sum('revenue');

        return $rows->mapWithKeys(function ($row) use ($total) {
            $revenue = round((float) $row->revenue, 2);

            return [
                (int) $row->group_id => [
                    // g.name is een vertaalbare JSON-kolom; de ruwe waarde
                    // moet nog door de vertaalslag heen.
                    'name' => TranslatableColumn::value((string) $row->name),
                    'revenue' => $revenue,
                    'units' => (int) $row->units,
                    'share_pct' => $total > 0 ? round($revenue / $total * 100, 1) : 0.0,
                ],
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, Signal>
     */
    protected function signals(array $groups, bool $hasPrevious): array
    {
        if (! $hasPrevious) {
            return [];
        }

        $signals = [];

        foreach ($groups as $group) {
            $shift = round($group['share_pct'] - $group['previous_share_pct'], 1);

            if (abs($shift) < self::SHARE_SHIFT) {
                continue;
            }

            $signals[] = new Signal(
                severity: $shift < 0 ? Signal::ATTENTION : Signal::OPPORTUNITY,
                title: $shift < 0
                    ? __(':groep verliest aandeel in de omzet', ['groep' => $group['name']])
                    : __(':groep wint aandeel in de omzet', ['groep' => $group['name']]),
                explanation: __('Het aandeel van deze groep in de totale omzet verschoof met :punten procentpunt.', ['punten' => abs($shift)]),
                numbers: [
                    __('Aandeel nu') => $group['share_pct'],
                    __('Aandeel vorige periode') => $group['previous_share_pct'],
                    __('Omzet') => $group['revenue'],
                ],
            );
        }

        // Zwaarste ernst eerst, net als SalesAnalysisReport::signals() dat
        // over alle analyses heen doet. usort is sinds PHP 8 stabiel, dus
        // bij gelijke ernst blijft de omzetvolgorde van $groups staan.
        usort($signals, fn (Signal $a, Signal $b) => Signal::weight($b->severity) <=> Signal::weight($a->severity));

        return $signals;
    }
}
