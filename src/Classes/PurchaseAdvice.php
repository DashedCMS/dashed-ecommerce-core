<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;

/**
 * Inkoopadvies uit de verkoopsnelheid: per product met voorraadbeheer de
 * voorraad, wat er in de periode verkocht is, de verkoop per dag, over
 * hoeveel dagen het op is, en hoeveel er bij moet om de horizon te halen.
 *
 * Alles in één query: de verkochte aantallen komen uit een subselect over
 * de orderregels van betaalde bestellingen (dezelfde statussen als de
 * omzetcijfers, zonder proforma's en creditorders), nooit uit modellen die
 * in PHP worden opgeteld. Een voorraadgroep (stock_source_product_id) deelt
 * één voorraad, dus de verkoop van de ontvangers telt bij de bron op en de
 * ontvangers zelf staan niet als losse regel in de lijst.
 */
class PurchaseAdvice
{
    public const PERIODS = [30, 60, 90];

    public const DEFAULT_PERIOD = 30;

    public const DEFAULT_HORIZON = 14;

    public static function horizonFor(?string $siteId = null): int
    {
        $value = (int) Customsetting::get('purchase_advice_horizon_days', $siteId ?? Sites::getActive(), (string) self::DEFAULT_HORIZON);

        return max(1, $value);
    }

    /**
     * @return array<int, array{id: int, name: string, sku: ?string, stock: int, sold: int, per_day: float, days_left: ?int, advice: int}>
     */
    public static function rows(int $periodDays, int $horizonDays, ?int $categoryId = null, bool $onlyWithinHorizon = true, ?string $siteId = null): array
    {
        $periodDays = max(1, $periodDays);
        $horizonDays = max(1, $horizonDays);
        $siteId ??= (string) Sites::getActive();
        $since = now()->subDays($periodDays)->startOfDay();

        $sold = DB::table('dashed__order_products as op')
            ->join('dashed__orders as o', 'o.id', '=', 'op.order_id')
            ->leftJoin('dashed__products as sp', 'sp.id', '=', 'op.product_id')
            ->whereIn('o.status', ['paid', 'waiting_for_confirmation', 'partially_paid'])
            ->whereNotIn('o.invoice_id', ['PROFORMA', 'RETURN'])
            ->where('o.site_id', $siteId)
            ->where('o.created_at', '>=', $since)
            ->whereNotNull('op.product_id')
            ->whereNotIn('op.sku', OrderStatistics::NON_PRODUCT_SKUS)
            ->selectRaw('COALESCE(sp.stock_source_product_id, op.product_id) as stock_product_id, SUM(op.quantity) as sold')
            ->groupByRaw('COALESCE(sp.stock_source_product_id, op.product_id)');

        $query = Product::query()
            ->thisSite($siteId)
            ->where('use_stock', 1)
            ->where('is_bundle', 0)
            ->whereNull('stock_source_product_id')
            ->leftJoinSub($sold, 'sold', 'sold.stock_product_id', '=', 'dashed__products.id')
            ->select(['dashed__products.id', 'dashed__products.name', 'dashed__products.sku', 'dashed__products.stock'])
            ->selectRaw('COALESCE(sold.sold, 0) as sold_quantity');

        if ($categoryId) {
            $query->whereHas('productCategories', fn ($q) => $q->where('dashed__product_categories.id', $categoryId));
        }

        $rows = [];
        foreach ($query->get() as $product) {
            $stock = (int) $product->stock;
            $soldQuantity = (int) $product->sold_quantity;
            $perDay = $soldQuantity / $periodDays;
            $daysLeft = $perDay > 0 ? (int) floor(max(0, $stock) / $perDay) : null;
            $advice = $perDay > 0 ? max(0, (int) ceil($perDay * $horizonDays) - max(0, $stock)) : 0;

            if ($onlyWithinHorizon && ($daysLeft === null || $daysLeft > $horizonDays)) {
                continue;
            }

            $rows[] = [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sku' => $product->sku ?: null,
                'stock' => $stock,
                'sold' => $soldQuantity,
                'per_day' => round($perDay, 2),
                'days_left' => $daysLeft,
                'advice' => $advice,
            ];
        }

        // Eerst wat het snelst op is; zonder verkoop (geen prognose) achteraan.
        usort($rows, fn (array $a, array $b) => [$a['days_left'] === null, $a['days_left'] ?? 0, -$a['per_day']]
            <=> [$b['days_left'] === null, $b['days_left'] ?? 0, -$b['per_day']]);

        return $rows;
    }
}
