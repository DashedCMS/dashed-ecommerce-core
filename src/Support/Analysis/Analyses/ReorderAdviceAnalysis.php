<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis\Analyses;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\OrderLineQuery;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

/**
 * Herbevoorrading-advies: op basis van de verkoopsnelheid in de periode en de
 * huidige voorraad — wanneer raakt een product op, en hoeveel bestel je bij om
 * de komende ~30 dagen te dekken. Alleen producten die voorraad bijhouden en in
 * de periode iets verkochten.
 */
class ReorderAdviceAnalysis implements SalesAnalysis
{
    protected const LIMIT = 50;

    /** Dekkingshorizon voor het bestel-advies. */
    protected const HORIZON_DAYS = 30;

    /** Onder deze dekking (dagen voorraad over) is het een signaal. */
    protected const LOW_COVER_DAYS = 14;

    public static function key(): string
    {
        return 'herbevoorrading';
    }

    public static function label(): string
    {
        return __('Herbevoorrading');
    }

    public static function group(): string
    {
        return 'inkoop';
    }

    public static function isAvailable(AnalysisContext $context): bool
    {
        return Product::query()->thisSite($context->siteId)->where('use_stock', true)->exists();
    }

    public function run(AnalysisContext $context): AnalysisResult
    {
        $days = max(1, $context->period->days());

        $soldPerProduct = OrderLineQuery::lines($context->period, $context->siteId)
            ->select('op.product_id', DB::raw('SUM(op.quantity) as sold'))
            ->groupBy('op.product_id')
            ->pluck('sold', 'op.product_id');

        if ($soldPerProduct->isEmpty()) {
            return AnalysisResult::empty();
        }

        $products = Product::query()
            ->thisSite($context->siteId)
            ->where('use_stock', true)
            ->whereIn('id', $soldPerProduct->keys()->all())
            ->get(['id', 'name', 'stock']);

        $rows = [];
        foreach ($products as $product) {
            $sold = (int) ($soldPerProduct[$product->id] ?? 0);
            if ($sold <= 0) {
                continue;
            }

            $velocity = $sold / $days; // eenheden per dag
            $stock = (int) $product->stock;
            $daysLeft = $velocity > 0 ? (int) floor($stock / $velocity) : null;
            $advised = max(0, (int) ceil($velocity * self::HORIZON_DAYS) - $stock);

            $rows[] = [
                'product_id' => (int) $product->id,
                'name' => (string) $product->name,
                'stock' => $stock,
                'sold' => $sold,
                'per_day' => round($velocity, 2),
                'days_left' => $daysLeft,
                'advised_reorder' => $advised,
            ];
        }

        // Meest urgent eerst: minste dagen voorraad over.
        usort($rows, fn (array $a, array $b) => ($a['days_left'] ?? PHP_INT_MAX) <=> ($b['days_left'] ?? PHP_INT_MAX));

        return new AnalysisResult(
            facts: [
                'products' => array_slice($rows, 0, self::LIMIT),
                'horizon_days' => self::HORIZON_DAYS,
                'period_days' => $days,
            ],
            signals: $this->signals($rows),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, Signal>
     */
    protected function signals(array $rows): array
    {
        $soon = array_values(array_filter(
            $rows,
            fn (array $r) => $r['days_left'] !== null && $r['days_left'] <= self::LOW_COVER_DAYS && $r['advised_reorder'] > 0,
        ));

        if ($soon === []) {
            return [];
        }

        $first = $soon[0];

        return [new Signal(
            severity: Signal::URGENT,
            title: __('Producten raken binnenkort op'),
            explanation: __(':aantal producten hebben op de huidige verkoopsnelheid nog :dagen dagen voorraad of minder. Het snelst op: :product.', [
                'aantal' => count($soon),
                'dagen' => self::LOW_COVER_DAYS,
                'product' => $first['name'],
            ]),
            numbers: [
                __('Producten') => count($soon),
                __('Snelst op (dagen)') => (int) $first['days_left'],
            ],
        )];
    }
}
