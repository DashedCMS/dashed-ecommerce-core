<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations\Strategies;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Services\CartSuggestions\CartProductSuggester;
use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;
use Dashed\DashedEcommerceCore\Services\Recommendations\ProductScore;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement;

/**
 * Wraps the existing tuned `CartProductSuggester` (free-shipping
 * gap-closing logic) as a RecommendationStrategy. Each suggested product
 * gets a positional score (1 - idx/count) so the first suggestion ranks
 * highest. Only applies to Cart/Checkout placements where the cart has
 * an open free-shipping gap; other placements skip.
 *
 * NOTE: the underlying `CartProductSuggester::suggest()` is the production
 * implementation today. Until the regression test in T17 confirms output
 * parity, callers in the cart Livewire should keep using the legacy
 * service directly — T29 is the cutover task.
 */
final class GapClosingStrategy implements RecommendationStrategy
{
    public function __construct(
        private readonly CartProductSuggester $legacy,
    ) {
    }

    public function key(): string
    {
        return 'gap_closing';
    }

    public function appliesTo(RecommendationContext $context): bool
    {
        return in_array($context->placement, [
            RecommendationPlacement::Cart,
            RecommendationPlacement::Checkout,
        ], true);
    }

    public function candidates(RecommendationContext $context): Collection
    {
        $cartProductIds = $context->currentProducts
            ->map(fn ($p) => (int) ($p->id ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($cartProductIds)) {
            return collect();
        }

        $cartTotal = (float) ($context->extra['cart_total'] ?? 0);

        try {
            $suggestions = $this->legacy->suggest(
                cartProductIds: $cartProductIds,
                cartTotal: $cartTotal,
                limit: $context->limit,
            );
        } catch (\Throwable) {
            return collect();
        }

        $total = $suggestions->count();
        if ($total === 0) {
            return collect();
        }

        return $suggestions->values()->map(function ($product, $idx) use ($total) {
            $score = 1 - ($idx / max(1, $total));
            return new ProductScore(
                product: $product,
                score: round($score, 4),
                reasons: ['gap_closing:idx=' . $idx],
            );
        });
    }

    public function defaultWeight(): float
    {
        return 0.6;
    }
}
