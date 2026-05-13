<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations\Strategies;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductCoPurchase;
use Dashed\DashedEcommerceCore\Services\Recommendations\ProductScore;
use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;

/**
 * Reads from the precomputed `dashed__product_co_purchase` table to
 * surface products commonly bought alongside the products already in
 * the context (cart, current viewed product, ordered products in an
 * email mailable, etc.).
 */
final class FrequentlyBoughtTogetherStrategy implements RecommendationStrategy
{
    public function key(): string
    {
        return 'frequently_bought_together';
    }

    public function appliesTo(RecommendationContext $context): bool
    {
        return $context->currentProducts->isNotEmpty();
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
            return collect();
        }

        // Each co-purchase row has product_a_id < product_b_id, so the
        // partner for a source product can be on either side.
        $rows = ProductCoPurchase::query()
            ->where(function ($q) use ($sourceIds) {
                $q->whereIn('product_a_id', $sourceIds)
                    ->orWhereIn('product_b_id', $sourceIds);
            })
            ->where('score', '>', 0)
            ->orderByDesc('score')
            ->limit($context->limit * 5) // over-fetch so the aggregator has room
            ->get();

        // Reduce to (partner_id => bestScore) - drop the source ids themselves.
        $partners = [];
        foreach ($rows as $row) {
            $a = (int) $row->product_a_id;
            $b = (int) $row->product_b_id;
            $partner = in_array($a, $sourceIds, true) ? $b : $a;
            if (in_array($partner, $sourceIds, true)) {
                continue;
            }
            $score = (float) $row->score;
            if (! isset($partners[$partner]) || $partners[$partner] < $score) {
                $partners[$partner] = $score;
            }
        }

        if (empty($partners)) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', array_keys($partners))
            ->get()
            ->keyBy('id');

        $out = collect();
        foreach ($partners as $partnerId => $score) {
            $product = $products->get($partnerId);
            if (! $product) {
                continue;
            }
            $out->push(new ProductScore(
                product: $product,
                score: $score,
                reasons: ['fbt:' . number_format($score, 3)],
            ));
        }

        return $out;
    }

    public function defaultWeight(): float
    {
        return 0.5;
    }
}
