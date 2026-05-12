<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations;

use Dashed\DashedEcommerceCore\Models\Product;

/**
 * One strategy's recommendation for a single product, with its raw score
 * (0.0-1.0) and the reasons that fed the ranking. The `reasons` array is
 * preserved end-to-end so the admin debug page can explain why a product
 * appears in a given placement.
 */
final readonly class ProductScore
{
    /**
     * @param  array<int, string>  $reasons
     */
    public function __construct(
        public Product $product,
        public float $score,
        public array $reasons = [],
    ) {
    }

    public function withScore(float $score): self
    {
        return new self($this->product, $score, $this->reasons);
    }

    public function mergeReasons(array $additional): self
    {
        return new self($this->product, $this->score, array_values(array_unique([...$this->reasons, ...$additional])));
    }
}
