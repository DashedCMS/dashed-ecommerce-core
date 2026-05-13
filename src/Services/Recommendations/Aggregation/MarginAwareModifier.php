<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations\Aggregation;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Services\Recommendations\ProductScore;

/**
 * Score tie-breaker that nudges high-margin products up a touch
 * (0.05 × normalised margin). Applied AFTER all strategies have been
 * weighted and summed - it's a thumb on the scale, not a primary signal.
 *
 * Final score is clamped to [0, 1] so weighted-sum aggregation stays
 * deterministic across long runs.
 */
final class MarginAwareModifier
{
    private const MAX_BUMP = 0.05;

    public function apply(Collection $scored): Collection
    {
        return $scored->map(function (ProductScore $score) {
            $product = $score->product;
            $price = (float) ($product->current_price ?? $product->price ?? 0);
            $cost = (float) ($product->cost_price ?? 0);

            if ($price <= 0 || $cost <= 0 || $cost >= $price) {
                return $score;
            }

            $margin = ($price - $cost) / $price;
            $bump = self::MAX_BUMP * max(0.0, min(1.0, $margin));
            $final = min(1.0, $score->score + $bump);

            return $score
                ->withScore($final)
                ->mergeReasons(['margin_bump:' . number_format($bump, 4)]);
        });
    }
}
