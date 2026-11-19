<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Services\ProductReviews\ProductReviewSettings;

class ProductReviewController
{
    public function show(int $orderProduct)
    {
        $line = OrderProduct::with(['order', 'product'])->findOrFail($orderProduct);

        abort_if(! $line->order || ! $line->product, 404);

        // Uitgezet nadat de mail wegging: de link doet dan niets meer.
        abort_if(! ProductReviewSettings::reviewsEnabled((string) $line->order->site_id), 404);

        if ($line->order->locale) {
            app()->setLocale($line->order->locale);
        }

        return view('dashed-ecommerce-core::product-reviews.write', ['orderProductId' => $line->getKey()]);
    }
}
