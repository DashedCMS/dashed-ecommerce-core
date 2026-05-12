<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations\Context;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement;

/**
 * Immutable input to RecommendationService::for(). Constructed via the
 * static builder so callers don't accidentally pass positional args in
 * the wrong order.
 *
 *     RecommendationContext::for(RecommendationPlacement::Cart)
 *         ->withCurrentProducts($cart->items->pluck('product'))
 *         ->withCustomer($cart->user)
 *         ->withLimit(4)
 *         ->build();
 */
final readonly class RecommendationContext
{
    public function __construct(
        public RecommendationPlacement $placement,
        public Collection $currentProducts,
        public mixed $customer = null,
        public ?string $locale = null,
        public ?string $siteId = null,
        public int $limit = 4,
        public array $excludedProductIds = [],
        public array $extra = [],
    ) {
    }

    public static function for(RecommendationPlacement $placement): RecommendationContextBuilder
    {
        return new RecommendationContextBuilder($placement);
    }
}
