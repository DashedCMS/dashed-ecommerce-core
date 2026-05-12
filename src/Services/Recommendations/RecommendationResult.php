<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations;

use Illuminate\Support\Collection;

/**
 * Final output of RecommendationService::for() — a ranked Collection of
 * Product instances plus the per-product trace ProductScores that fed
 * the ranking. Callers that only need products use `->products`; the
 * `->explain()` admin debug page reads `->scores`.
 */
final readonly class RecommendationResult
{
    /**
     * @param  Collection<int, \Dashed\DashedEcommerceCore\Models\Product>  $products
     * @param  Collection<int, ProductScore>  $scores
     */
    public function __construct(
        public Collection $products,
        public Collection $scores,
    ) {
    }

    public static function empty(): self
    {
        return new self(collect(), collect());
    }

    public function isEmpty(): bool
    {
        return $this->products->isEmpty();
    }
}
