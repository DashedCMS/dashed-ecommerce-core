<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations;

use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;
use Dashed\DashedEcommerceCore\Services\Recommendations\Aggregation\MarginAwareModifier;

/**
 * Aggregates per-strategy ProductScores into a single ranked product
 * list for the given placement. Reads the active strategy stack from
 * `RecommendationRegistry`, applies placement-specific weights from
 * `PLACEMENT_DEFAULTS`, runs the MarginAware tie-break, and truncates
 * to the requested limit.
 *
 * v1.1 (out of scope here) will read placement weights from
 * `Customsetting::get("recommendation_stack_{$placement}")` so admins can
 * tune the mix per site without code changes.
 */
class RecommendationService
{
    /**
     * Per-placement strategy stack: strategy-key => weight.
     * Hard-coded in v1 — admin override is v1.1.
     */
    public const PLACEMENT_DEFAULTS = [
        'ProductDetail' => [
            'frequently_bought_together' => 0.5,
            'category_affinity' => 0.3,
            'custom_manual' => 0.2,
        ],
        'Cart' => [
            'gap_closing' => 0.6,
            'frequently_bought_together' => 0.4,
        ],
        'Checkout' => [
            'gap_closing' => 0.7,
            'frequently_bought_together' => 0.3,
        ],
        'EmailOrderHandled' => [
            'frequently_bought_together' => 0.5,
            'category_affinity' => 0.5,
        ],
        'EmailAbandonedCart' => [
            'frequently_bought_together' => 0.6,
            'category_affinity' => 0.4,
        ],
        'EmailPopupFollowUp' => [
            'category_affinity' => 0.7,
            'frequently_bought_together' => 0.3,
        ],
        'Popup' => [
            'category_affinity' => 0.5,
            'custom_manual' => 0.5,
        ],
    ];

    public function __construct(
        protected RecommendationRegistry $registry,
        protected MarginAwareModifier $marginModifier,
    ) {
    }

    /**
     * Same input as `for()` but returns the per-strategy breakdown alongside
     * the final ranking. Read by the admin debug page so an operator can
     * understand why a given product appears (or doesn't) for a placement.
     *
     * @return array{
     *   strategies: array<string, array<int, array{product_id: int, name: string, raw: float, weighted: float, reasons: array<int, string>}>>,
     *   ranking: array<int, array{product_id: int, name: string, score: float, reasons: array<int, string>}>
     * }
     */
    public function explain(RecommendationContext $context): array
    {
        $strategies = $this->registry->forPlacement($context->placement);
        $weights = self::PLACEMENT_DEFAULTS[$context->placement->name] ?? [];

        $breakdown = [];
        foreach ($strategies as $strategy) {
            if (! $strategy->appliesTo($context)) {
                $breakdown[$strategy->key()] = [];

                continue;
            }
            $weight = $weights[$strategy->key()] ?? $strategy->defaultWeight();

            try {
                $candidates = $strategy->candidates($context);
            } catch (\Throwable) {
                $breakdown[$strategy->key()] = [];

                continue;
            }
            $breakdown[$strategy->key()] = $candidates
                ->filter(fn ($s) => $s instanceof ProductScore)
                ->map(fn (ProductScore $s) => [
                    'product_id' => (int) ($s->product->id ?? 0),
                    'name' => (string) ($s->product->name ?? ''),
                    'raw' => round($s->score, 4),
                    'weighted' => round($s->score * $weight, 4),
                    'reasons' => $s->reasons,
                ])
                ->values()
                ->all();
        }

        $result = $this->for($context);
        $ranking = $result->scores->map(fn (ProductScore $s) => [
            'product_id' => (int) ($s->product->id ?? 0),
            'name' => (string) ($s->product->name ?? ''),
            'score' => round($s->score, 4),
            'reasons' => $s->reasons,
        ])->values()->all();

        return [
            'strategies' => $breakdown,
            'ranking' => $ranking,
        ];
    }

    public function for(RecommendationContext $context): RecommendationResult
    {
        $strategies = $this->registry->forPlacement($context->placement);

        if (empty($strategies)) {
            return RecommendationResult::empty($context->resolvedHeading());
        }

        $weights = self::PLACEMENT_DEFAULTS[$context->placement->name] ?? [];

        /** @var array<int, ProductScore> $aggregated */
        $aggregated = [];

        foreach ($strategies as $strategy) {
            if (! $strategy->appliesTo($context)) {
                continue;
            }

            $weight = $weights[$strategy->key()] ?? $strategy->defaultWeight();

            try {
                $candidates = $strategy->candidates($context);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            foreach ($candidates as $score) {
                if (! $score instanceof ProductScore) {
                    continue;
                }
                $product = $score->product;
                $id = (int) ($product->id ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $weighted = $score->score * $weight;

                if (isset($aggregated[$id])) {
                    $existing = $aggregated[$id];
                    $aggregated[$id] = new ProductScore(
                        product: $product,
                        score: min(1.0, $existing->score + $weighted),
                        reasons: array_values(array_unique([...$existing->reasons, ...$score->reasons, $strategy->key() . ':' . number_format($weighted, 3)])),
                    );
                } else {
                    $aggregated[$id] = new ProductScore(
                        product: $product,
                        score: min(1.0, $weighted),
                        reasons: [...$score->reasons, $strategy->key() . ':' . number_format($weighted, 3)],
                    );
                }
            }
        }

        $scored = collect(array_values($aggregated));
        $scored = $this->marginModifier->apply($scored);

        $excluded = array_flip($context->excludedProductIds);
        $contextIds = $context->currentProducts
            ->map(fn ($p) => (int) ($p->id ?? 0))
            ->filter()
            ->all();
        foreach ($contextIds as $cid) {
            $excluded[$cid] = true;
        }

        $scored = $scored
            ->filter(function (ProductScore $s) use ($excluded) {
                $id = (int) ($s->product->id ?? 0);
                if ($id <= 0 || isset($excluded[$id])) {
                    return false;
                }
                $product = $s->product;
                if (isset($product->public) && ! $product->public) {
                    return false;
                }
                if (($product->use_stock ?? false) && ! ($product->in_stock ?? true)) {
                    return false;
                }

                return true;
            })
            ->sortByDesc(fn (ProductScore $s) => $s->score)
            ->take($context->limit)
            ->values();

        return new RecommendationResult(
            products: $scored->map(fn (ProductScore $s) => $s->product),
            scores: $scored,
            heading: $context->resolvedHeading(),
        );
    }
}
