<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations\Strategies;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Services\Recommendations\ProductScore;
use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;

/**
 * Recommends products from the same productCategories as the products
 * already in context. Score = 0.6 base + 0.1 per shared category, capped
 * at 1.0. When the context has no products (e.g. a popup with no anchor),
 * falls back to globally hot products in the active site's catalog -
 * still under the same key so the placement weight applies.
 */
final class CategoryAffinityStrategy implements RecommendationStrategy
{
    public function key(): string
    {
        return 'category_affinity';
    }

    public function appliesTo(RecommendationContext $context): bool
    {
        return true;
    }

    public function candidates(RecommendationContext $context): Collection
    {
        $sourceIds = $context->currentProducts
            ->map(fn ($p) => (int) ($p->id ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($sourceIds)) {
            // Cold start: bestsellers fallback (top by created_at desc as a
            // proxy; a richer signal can land in v1.1 when we have order
            // aggregates wired in).
            $products = Product::query()
                ->where('public', 1)
                ->orderByDesc('created_at')
                ->limit($context->limit * 3)
                ->get();

            return $products->map(fn ($p) => new ProductScore(
                product: $p,
                score: 0.4,
                reasons: ['category_affinity:cold_start'],
            ));
        }

        // Resolve source-product categories (use the productCategories pivot).
        $sourceProducts = Product::query()
            ->whereIn('id', $sourceIds)
            ->with('productCategories:id')
            ->get();

        $categoryIds = $sourceProducts
            ->flatMap(fn ($p) => $p->productCategories->pluck('id'))
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        $candidates = Product::query()
            ->where('public', 1)
            ->whereNotIn('id', $sourceIds)
            ->whereHas('productCategories', fn ($q) => $q->whereIn('id', $categoryIds))
            ->with(['productCategories:id'])
            ->limit($context->limit * 5)
            ->get();

        return $candidates->map(function ($product) use ($categoryIds) {
            $shared = $product->productCategories
                ->pluck('id')
                ->intersect($categoryIds)
                ->count();
            $score = min(1.0, 0.6 + ($shared * 0.1));

            return new ProductScore(
                product: $product,
                score: $score,
                reasons: ['category_affinity:shared=' . $shared],
            );
        });
    }

    public function defaultWeight(): float
    {
        return 0.4;
    }
}
