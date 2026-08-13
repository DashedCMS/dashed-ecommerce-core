<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis\Analyses;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\OrderLineQuery;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

/**
 * Producten die voorraad hebben en in de periode niets deden. Gesorteerd op
 * voorraadwaarde, want dat is het bedrag dat stilligt.
 *
 * De waarde rekent op de verkoopprijs en niet op de inkoopprijs; die laatste
 * is op de meeste shops niet ingevuld. Het is dus de omzet die stilligt, niet
 * het ingekochte kapitaal.
 */
class UnsoldStockAnalysis implements SalesAnalysis
{
    protected const LIMIT = 50;

    /** Vanaf dit bedrag aan stilliggende voorraad is het een signaal. */
    protected const VALUE_THRESHOLD = 1000.0;

    public static function key(): string
    {
        return 'niets-verkocht';
    }

    public static function label(): string
    {
        return __('Niets verkocht');
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
        $soldProductIds = OrderLineQuery::lines($context->period, $context->siteId)
            ->distinct()
            ->pluck('op.product_id')
            ->all();

        $products = Product::query()
            ->where('stock', '>', 0)
            ->when($soldProductIds !== [], fn ($query) => $query->whereNotIn('id', $soldProductIds))
            ->get(['id', 'name', 'stock', 'current_price', 'price']);

        $rows = [];
        $totalValue = 0.0;

        foreach ($products as $product) {
            $unitPrice = (float) ($product->getRawOriginal('current_price') ?? $product->getRawOriginal('price') ?? 0);
            $value = round($unitPrice * (int) $product->stock, 2);
            $totalValue += $value;

            $rows[] = [
                'product_id' => (int) $product->id,
                'name' => (string) $product->name,
                'stock' => (int) $product->stock,
                'stock_value' => $value,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['stock_value'] <=> $a['stock_value']);

        $totalValue = round($totalValue, 2);

        return new AnalysisResult(
            facts: [
                'products' => array_slice($rows, 0, self::LIMIT),
                'total_value' => $totalValue,
            ],
            signals: $this->signals($rows, $totalValue),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, Signal>
     */
    protected function signals(array $rows, float $totalValue): array
    {
        if ($totalValue < self::VALUE_THRESHOLD || $rows === []) {
            return [];
        }

        return [new Signal(
            severity: Signal::ATTENTION,
            title: __('Er ligt voorraad stil die niets verkocht'),
            explanation: __(':aantal producten met voorraad verkochten deze periode niets. De grootste post is :product.', [
                'aantal' => count($rows),
                'product' => $rows[0]['name'],
            ]),
            numbers: [
                __('Waarde') => $totalValue,
                __('Producten') => count($rows),
            ],
        )];
    }
}
