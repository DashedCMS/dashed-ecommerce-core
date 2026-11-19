<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\ProductReviews;

use Livewire\Component;
use Livewire\Attributes\Locked;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductReview;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ProductReviewStats;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ProductReviewSettings;

/**
 * Gemiddelde, verdeling en de goedgekeurde reviews op de productpagina.
 * Thema: <livewire:product-reviews.product-reviews :product="$product" />.
 * Zelfde HTML voor elke bezoeker, dus veilig in de paginacache; "meer
 * tonen" is een Livewire-verzoek.
 */
class ProductReviews extends Component
{
    public const PER_PAGE = 5;

    #[Locked]
    public int $productId;

    public int $limit = self::PER_PAGE;

    public function mount(Product|int $product): void
    {
        $this->productId = $product instanceof Product ? (int) $product->getKey() : $product;
    }

    public function loadMore(): void
    {
        $this->limit = min($this->limit + self::PER_PAGE, 200);
    }

    public function render()
    {
        $product = ProductReviewSettings::reviewsEnabled() ? Product::find($this->productId) : null;
        $stats = $product ? ProductReviewStats::for($product) : null;
        $reviews = $product && $stats['count'] > 0
            ? ProductReview::query()->approved()->forProduct($product)->latest('approved_at')->latest('id')->limit($this->limit)->get()
            : collect();

        return view('dashed-ecommerce-core::livewire.frontend.product-reviews.product-reviews', [
            'enabled' => (bool) $product,
            'stats' => $stats,
            'reviews' => $reviews,
            'hasMore' => $stats && $stats['count'] > $reviews->count(),
        ]);
    }
}
