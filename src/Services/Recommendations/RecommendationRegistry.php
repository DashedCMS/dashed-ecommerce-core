<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations;

use Dashed\DashedEcommerceCore\Services\Recommendations\Strategies\RecommendationStrategy;

/**
 * In-memory registry of recommendation strategies registered via
 * `cms()->registerRecommendationStrategy(...)`. The RecommendationService
 * reads from here per request, filtering by placement.
 */
class RecommendationRegistry
{
    /** @var array<string, array{strategy: RecommendationStrategy, placements: ?array<int, RecommendationPlacement>}> */
    protected array $strategies = [];

    /**
     * @param  array<int, RecommendationPlacement>|null  $placements  Restrict to these placements. Null = all.
     */
    public function register(RecommendationStrategy $strategy, ?array $placements = null): self
    {
        $this->strategies[$strategy->key()] = [
            'strategy' => $strategy,
            'placements' => $placements,
        ];

        return $this;
    }

    /**
     * @return array<string, RecommendationStrategy>
     */
    public function all(): array
    {
        return array_map(fn ($entry) => $entry['strategy'], $this->strategies);
    }

    /**
     * @return array<string, RecommendationStrategy>
     */
    public function forPlacement(RecommendationPlacement $placement): array
    {
        $result = [];
        foreach ($this->strategies as $key => $entry) {
            if ($entry['placements'] === null || in_array($placement, $entry['placements'], true)) {
                $result[$key] = $entry['strategy'];
            }
        }

        return $result;
    }

    public function get(string $key): ?RecommendationStrategy
    {
        return $this->strategies[$key]['strategy'] ?? null;
    }

    public function flush(): void
    {
        $this->strategies = [];
    }
}
