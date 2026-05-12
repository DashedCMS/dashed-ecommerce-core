<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations\Strategies;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;
use Dashed\DashedEcommerceCore\Services\Recommendations\ProductScore;

/**
 * Reads from the admin-curated cross-sell list on each Product
 * (`Product::crossSellProducts` relation). This is the "trust the
 * merchandiser" strategy: highest score for explicitly-picked partners
 * so that hand-curated bundles always rank.
 */
final class CustomManualStrategy implements RecommendationStrategy
{
    public function key(): string
    {
        return 'custom_manual';
    }

    public function appliesTo(RecommendationContext $context): bool
    {
        return $context->currentProducts->isNotEmpty();
    }

    public function candidates(RecommendationContext $context): Collection
    {
        $out = collect();
        $seen = [];

        foreach ($context->currentProducts as $product) {
            if (! method_exists($product, 'crossSellProducts')) {
                continue;
            }
            try {
                $relation = $product->crossSellProducts;
            } catch (\Throwable) {
                continue;
            }
            if (! $relation) {
                continue;
            }

            foreach ($relation as $cross) {
                $id = (int) ($cross->id ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $out->push(new ProductScore(
                    product: $cross,
                    score: 0.9,
                    reasons: ['custom_manual:curated'],
                ));
            }
        }

        return $out;
    }

    public function defaultWeight(): float
    {
        return 0.2;
    }
}
