<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\CartSuggestions;

use Dashed\DashedEcommerceCore\Helpers\FreeShippingHelper;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CartProductSuggester
{
    public function __construct(
        private readonly FreeShippingHelper $freeShippingHelper = new FreeShippingHelper(),
    ) {}

    /**
     * @param  array<int>  $cartProductIds
     */
    public function suggest(
        array $cartProductIds,
        float $cartTotal,
        int $limit = 6,
        int $boostSlots = 3,
        bool $requireInStock = true,
        bool $fallbackRandom = true,
        float $gapMinFactor = 0.8,
        float $gapMaxFactor = 1.5,
    ): Collection {
        $cartProductIds = array_values(array_unique(array_filter($cartProductIds)));

        if ($cartProductIds === []) {
            return collect();
        }

        $progress = $this->freeShippingHelper->progress($cartTotal);
        $gap = (float) $progress['gap'];

        $priceRange = $gap > 0
            ? ['low' => $gap * $gapMinFactor, 'high' => $gap * $gapMaxFactor]
            : null;

        $cartProducts = Product::query()
            ->whereIn('id', $cartProductIds)
            ->with(['crossSellProducts', 'suggestedProducts', 'productGroup.crossSellProducts', 'productGroup.suggestedProducts', 'productCategories'])
            ->get();

        $pool = $this->buildPool(
            cartProducts: $cartProducts,
            cartProductIds: $cartProductIds,
            limit: $limit,
            requireInStock: $requireInStock,
            fallbackRandom: $fallbackRandom,
            priceRange: $priceRange,
        );

        if ($pool->isEmpty()) {
            return collect();
        }

        $deduped = $this->dedupeByProductGroup($pool);

        $ranked = $priceRange !== null
            ? $this->boostInRangeBestSellers($deduped, $gap, $priceRange, $boostSlots)
            : $deduped->each(fn (Product $p) => $p->is_gap_closer = false);

        return $ranked->take($limit)->values();
    }

    /**
     * Build candidate pool. When a price-range is active, every step prefers
     * in-range products first; if those don't fill the limit, out-of-range is
     * added as filler.
     *
     * @param  array<int>  $cartProductIds
     * @param  array{low: float, high: float}|null  $priceRange
     */
    private function buildPool(
        Collection $cartProducts,
        array $cartProductIds,
        int $limit,
        bool $requireInStock,
        bool $fallbackRandom,
        ?array $priceRange,
    ): Collection {
        $explicit = collect();
        foreach ($cartProducts as $product) {
            $explicit = $explicit
                ->concat($product->crossSellProducts)
                ->concat($product->suggestedProducts)
                ->concat($product->productGroup?->crossSellProducts ?? [])
                ->concat($product->productGroup?->suggestedProducts ?? []);
        }

        $explicit = $explicit
            ->unique('id')
            ->reject(fn (Product $p) => in_array($p->id, $cartProductIds, true));

        if ($requireInStock) {
            $explicit = $explicit->filter(fn (Product $p) => ! $p->use_stock || $p->in_stock);
        }

        if ($priceRange !== null) {
            [$inRange, $outOfRange] = $this->partitionByRange($explicit, $priceRange);
            $pool = $inRange;
        } else {
            $pool = $explicit;
            $outOfRange = collect();
        }

        $categoryIds = $cartProducts
            ->flatMap(fn (Product $p) => $p->productCategories->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $distinctGroups = fn (Collection $coll): int => $coll
            ->pluck('product_group_id')
            ->filter()
            ->unique()
            ->count() + $coll->whereNull('product_group_id')->count();

        if ($distinctGroups($pool) < $limit && ! empty($categoryIds)) {
            $excluded = $pool->pluck('id')->concat($outOfRange->pluck('id'))->concat($cartProductIds)->all();

            $catProducts = $this->categoryQuery($categoryIds, $excluded, $requireInStock, $priceRange)
                ->limit(max($limit * 10, 50))
                ->get();

            $pool = $pool->concat($catProducts)->unique('id')->values();
        }

        if ($fallbackRandom && $distinctGroups($pool) < $limit) {
            $excluded = $pool->pluck('id')->concat($outOfRange->pluck('id'))->concat($cartProductIds)->all();

            $randomProducts = $this->randomQuery($excluded, $requireInStock, $priceRange)
                ->limit(max($limit * 10, 50))
                ->get();

            $pool = $pool->concat($randomProducts)->unique('id')->values();
        }

        if ($priceRange !== null && $distinctGroups($pool) < $limit) {
            $excluded = $pool->pluck('id')->concat($cartProductIds)->all();

            $filler = collect()->concat($outOfRange);

            if ($distinctGroups($filler) < $limit) {
                $extraExcluded = $filler->pluck('id')->concat($excluded)->all();
                $extra = Product::query()
                    ->thisSite()
                    ->publicShowable()
                    ->whereNotIn('id', $extraExcluded)
                    ->orderByDesc('total_purchases')
                    ->limit(max($limit * 10, 50))
                    ->get();
                if ($requireInStock) {
                    $extra = $extra->filter(fn (Product $p) => ! $p->use_stock || $p->in_stock);
                }
                $filler = $filler->concat($extra);
            }

            $pool = $pool->concat($filler)->unique('id')->values();
        }

        return $pool;
    }

    /**
     * @param  array{low: float, high: float}  $priceRange
     * @return array{0: Collection, 1: Collection}
     */
    private function partitionByRange(Collection $products, array $priceRange): array
    {
        [$in, $out] = $products->partition(
            fn (Product $p) => (float) $p->current_price >= $priceRange['low']
                && (float) $p->current_price <= $priceRange['high']
        );

        return [$in->values(), $out->values()];
    }

    /**
     * @param  array<int>  $categoryIds
     * @param  array<int>  $excluded
     * @param  array{low: float, high: float}|null  $priceRange
     */
    private function categoryQuery(array $categoryIds, array $excluded, bool $requireInStock, ?array $priceRange): Builder
    {
        $query = Product::query()
            ->thisSite()
            ->publicShowable()
            ->whereNotIn('id', $excluded)
            ->whereHas('productCategories', fn ($q) => $q->whereIn('dashed__product_categories.id', $categoryIds))
            ->orderByDesc('total_purchases');

        if ($priceRange !== null) {
            $query->whereBetween('current_price', [$priceRange['low'], $priceRange['high']]);
        }

        if ($requireInStock) {
            $query->where(fn ($q) => $q->where('use_stock', false)->orWhere('in_stock', true));
        }

        return $query;
    }

    /**
     * @param  array<int>  $excluded
     * @param  array{low: float, high: float}|null  $priceRange
     */
    private function randomQuery(array $excluded, bool $requireInStock, ?array $priceRange): Builder
    {
        $query = Product::query()
            ->thisSite()
            ->publicShowable()
            ->whereNotIn('id', $excluded)
            ->orderByDesc('total_purchases');

        if ($priceRange !== null) {
            $query->whereBetween('current_price', [$priceRange['low'], $priceRange['high']]);
        }

        if ($requireInStock) {
            $query->where(fn ($q) => $q->where('use_stock', false)->orWhere('in_stock', true));
        }

        return $query;
    }

    /**
     * Per ProductGroup: kies de variant met de hoogste total_purchases (best-seller).
     * Producten zonder ProductGroup blijven 1-op-1 behouden.
     */
    private function dedupeByProductGroup(Collection $pool): Collection
    {
        $byGroup = $pool->groupBy(fn (Product $p) => $p->product_group_id ?? 'product:'.$p->id);

        return $byGroup
            ->map(function (Collection $variants) {
                if ($variants->count() === 1) {
                    return $variants->first();
                }

                return $variants
                    ->sortByDesc(fn (Product $p) => (int) ($p->total_purchases ?? 0))
                    ->first();
            })
            ->values();
    }

    /**
     * Sorteer in-range best-sellers naar voren (eerste $boostSlots posities),
     * markeer ze als gap-closer voor de UI badge.
     *
     * @param  array{low: float, high: float}  $priceRange
     */
    private function boostInRangeBestSellers(
        Collection $pool,
        float $gap,
        array $priceRange,
        int $boostSlots,
    ): Collection {
        [$inRange, $outOfRange] = $this->partitionByRange($pool, $priceRange);

        $inRange = $inRange
            ->sortByDesc(fn (Product $p) => (int) ($p->total_purchases ?? 0))
            ->values();

        $inRange->each(fn (Product $p) => $p->is_gap_closer = true);
        $outOfRange->each(fn (Product $p) => $p->is_gap_closer = false);

        $taken = $inRange->take($boostSlots);
        $rest = $inRange->slice($boostSlots);

        return $taken->concat($outOfRange)->concat($rest)->values();
    }
}
