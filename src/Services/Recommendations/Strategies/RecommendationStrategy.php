<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations\Strategies;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;

/**
 * Contract for one recommendation algorithm. Each strategy emits
 * `ProductScore`s that the central `RecommendationService` aggregates
 * (with placement-specific weights + the MarginAware modifier) into a
 * final ranked product list.
 *
 * Strategies are stateless and registered as singletons in the container
 * via `cms()->registerRecommendationStrategy()` from a service provider.
 */
interface RecommendationStrategy
{
    /**
     * Unique slug used in PLACEMENT_DEFAULTS, log reasons, and the admin
     * debug page. Stable string, snake_case (e.g. `'frequently_bought_together'`).
     */
    public function key(): string;

    /**
     * Skip-fast guard: cheap check to determine whether this strategy has
     * anything useful to contribute for the given context. The aggregator
     * skips `candidates()` entirely when this returns false.
     */
    public function appliesTo(RecommendationContext $context): bool;

    /**
     * Generate candidate scores. Return an empty Collection when no
     * candidates are found; never null. Scores should be in the 0.0-1.0
     * range so weighted-sum aggregation behaves consistently.
     *
     * @return Collection<int, \Dashed\DashedEcommerceCore\Services\Recommendations\ProductScore>
     */
    public function candidates(RecommendationContext $context): Collection;

    /**
     * Default weight when registered with no per-placement override. The
     * aggregator multiplies each candidate's score by this weight when
     * summing across strategies for one placement.
     */
    public function defaultWeight(): float;
}
