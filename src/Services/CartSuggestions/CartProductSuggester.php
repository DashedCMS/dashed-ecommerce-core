<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\CartSuggestions;

use Dashed\DashedEcommerceCore\Helpers\FreeShippingHelper;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Support\Collection;

class CartProductSuggester
{
    public function __construct(
        private readonly FreeShippingHelper $freeShippingHelper = new FreeShippingHelper(),
    ) {}

    /**
     * Build suggestions for the given cart state.
     *
     * @param  array<int>  $cartProductIds  Product ids currently in cart
     * @param  float  $cartTotal  Cart total used for gap-detection
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

        $pool = $this->buildCandidatePool(
            $cartProductIds,
            $limit,
            $requireInStock,
            $fallbackRandom,
        );

        if ($pool->isEmpty()) {
            return collect();
        }

        $progress = $this->freeShippingHelper->progress($cartTotal);
        $gap = (float) $progress['gap'];

        $ranked = $gap > 0
            ? $this->boostGapClosers($pool, $gap, $gapMinFactor, $gapMaxFactor, $boostSlots)
            : $pool->each(fn (Product $p) => $p->is_gap_closer = false);

        return $ranked->take($limit)->values();
    }

    /**
     * @param  array<int>  $cartProductIds
     */
    private function buildCandidatePool(
        array $cartProductIds,
        int $limit,
        bool $requireInStock,
        bool $fallbackRandom,
    ): Collection {
        $cartProducts = Product::query()
            ->whereIn('id', $cartProductIds)
            ->with(['crossSellProducts', 'suggestedProducts', 'productGroup.crossSellProducts', 'productGroup.suggestedProducts', 'productCategories'])
            ->get();

        $pool = collect();

        foreach ($cartProducts as $product) {
            $pool = $pool
                ->concat($product->crossSellProducts)
                ->concat($product->suggestedProducts)
                ->concat($product->productGroup?->crossSellProducts ?? [])
                ->concat($product->productGroup?->suggestedProducts ?? []);
        }

        $pool = $pool->unique('id')->reject(fn (Product $p) => in_array($p->id, $cartProductIds, true));

        if ($pool->count() < $limit) {
            $needed = $limit - $pool->count();
            $excluded = $pool->pluck('id')->concat($cartProductIds)->all();

            $categoryIds = $cartProducts
                ->flatMap(fn (Product $p) => $p->productCategories->pluck('id'))
                ->unique()
                ->values()
                ->all();

            if (! empty($categoryIds)) {
                $catProducts = Product::query()
                    ->thisSite()
                    ->publicShowable()
                    ->whereNotIn('id', $excluded)
                    ->whereHas('productCategories', fn ($q) => $q->whereIn('dashed__product_categories.id', $categoryIds))
                    ->inRandomOrder()
                    ->limit($needed)
                    ->get();

                $pool = $pool->concat($catProducts);
            }
        }

        if ($fallbackRandom && $pool->count() < $limit) {
            $needed = $limit - $pool->count();
            $excluded = $pool->pluck('id')->concat($cartProductIds)->all();

            $randomProducts = Product::query()
                ->thisSite()
                ->publicShowable()
                ->whereNotIn('id', $excluded)
                ->inRandomOrder()
                ->limit($needed)
                ->get();

            $pool = $pool->concat($randomProducts);
        }

        if ($requireInStock) {
            $pool = $pool->filter(fn (Product $p) => ! $p->use_stock || $p->stock > 0);
        }

        return $pool->unique('id')->values();
    }

    private function boostGapClosers(
        Collection $pool,
        float $gap,
        float $minFactor,
        float $maxFactor,
        int $boostSlots,
    ): Collection {
        $low = $gap * $minFactor;
        $high = $gap * $maxFactor;

        [$gapClosers, $rest] = $pool->partition(
            fn (Product $p) => (float) $p->current_price >= $low && (float) $p->current_price <= $high
        );

        $gapClosers = $gapClosers->sortBy(fn (Product $p) => abs((float) $p->current_price - $gap))->values();
        $gapClosers->each(fn (Product $p) => $p->is_gap_closer = true);
        $rest->each(fn (Product $p) => $p->is_gap_closer = false);

        $taken = $gapClosers->take($boostSlots);

        return $taken->concat($rest)->concat($gapClosers->slice($boostSlots))->unique('id')->values();
    }
}
