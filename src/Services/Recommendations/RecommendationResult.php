<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations;

use Illuminate\Support\Collection;

/**
 * Final output of RecommendationService::for() — a ranked Collection of
 * Product instances plus the per-product trace ProductScores that fed
 * the ranking. Callers that only need products use `->products`; the
 * `->explain()` admin debug page reads `->scores`.
 *
 * `->heading` is the copy meant to be rendered above the products grid
 * (e.g. "Vaak samen gekocht"). Resolved from the placement default or
 * the per-call override on the context.
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
        public ?string $heading = null,
    ) {
    }

    public static function empty(?string $heading = null): self
    {
        return new self(collect(), collect(), $heading);
    }

    public function isEmpty(): bool
    {
        return $this->products->isEmpty();
    }
}
