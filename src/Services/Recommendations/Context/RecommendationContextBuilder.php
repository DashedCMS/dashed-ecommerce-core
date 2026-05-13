<?php

namespace Dashed\DashedEcommerceCore\Services\Recommendations\Context;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement;

class RecommendationContextBuilder
{
    protected Collection $currentProducts;
    protected mixed $customer = null;
    protected ?string $locale = null;
    protected ?string $siteId = null;
    protected int $limit = 4;
    /** @var array<int, int> */
    protected array $excludedProductIds = [];
    /** @var array<string, mixed> */
    protected array $extra = [];
    protected ?string $heading = null;

    public function __construct(protected RecommendationPlacement $placement)
    {
        $this->currentProducts = collect();
    }

    public function withCurrentProducts(iterable $products): self
    {
        $this->currentProducts = collect($products)->filter()->values();
        return $this;
    }

    public function withCustomer(mixed $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function withLocale(?string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function withSiteId(?string $siteId): self
    {
        $this->siteId = $siteId;
        return $this;
    }

    public function withLimit(int $limit): self
    {
        $this->limit = max(1, $limit);
        return $this;
    }

    public function withExcluded(array $productIds): self
    {
        $this->excludedProductIds = array_values(array_unique(array_map('intval', $productIds)));
        return $this;
    }

    public function withExtra(string $key, mixed $value): self
    {
        $this->extra[$key] = $value;
        return $this;
    }

    /**
     * Override the default heading shown above the products grid. Pass null
     * (or skip the call) to use RecommendationPlacement::heading() as the
     * default copy.
     */
    public function withHeading(?string $heading): self
    {
        $this->heading = $heading !== null ? trim($heading) : null;
        if ($this->heading === '') {
            $this->heading = null;
        }
        return $this;
    }

    public function build(): RecommendationContext
    {
        return new RecommendationContext(
            placement: $this->placement,
            currentProducts: $this->currentProducts,
            customer: $this->customer,
            locale: $this->locale,
            siteId: $this->siteId,
            limit: $this->limit,
            excludedProductIds: $this->excludedProductIds,
            extra: $this->extra,
            heading: $this->heading,
        );
    }
}
