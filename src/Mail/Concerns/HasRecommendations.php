<?php

namespace Dashed\DashedEcommerceCore\Mail\Concerns;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationService;
use Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement;
use Dashed\DashedEcommerceCore\Services\Recommendations\Context\RecommendationContext;

/**
 * Compose into any Mailable that wants to surface recommendations in its
 * template. The mailable's view can then call:
 *
 *     @include('dashed-ecommerce-core::email.recommendations', [
 *         'products' => $message->recommendationsFor(
 *             \Dashed\DashedEcommerceCore\Services\Recommendations\RecommendationPlacement::EmailOrderHandled,
 *             $order->orderProducts->pluck('product')->filter(),
 *             4,
 *             $order->user,
 *         ),
 *     ])
 */
trait HasRecommendations
{
    public function recommendationsFor(
        RecommendationPlacement $placement,
        iterable $currentProducts = [],
        int $limit = 4,
        mixed $customer = null,
    ): Collection {
        $result = app(RecommendationService::class)->for(
            RecommendationContext::for($placement)
                ->withCurrentProducts($currentProducts)
                ->withCustomer($customer)
                ->withLimit($limit)
                ->build()
        );

        return $result->products;
    }
}
