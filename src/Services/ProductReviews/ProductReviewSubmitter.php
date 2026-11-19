<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\ProductReviews;

use Illuminate\Database\QueryException;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductReview;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductReviewResource;

/**
 * Legt een review van een geverifieerde koper vast. Altijd eerst ter
 * beoordeling: niets komt op de productpagina zonder dat een beheerder het
 * goedkeurde. Eén review per orderregel; de unieke index op
 * `order_product_id` vangt een dubbele klik.
 */
class ProductReviewSubmitter
{
    /**
     * @param  array{rating: int, title?: ?string, body?: ?string, name: string}  $data
     */
    public static function fromOrderProduct(OrderProduct $line, array $data): ?ProductReview
    {
        if (ProductReview::query()->where('order_product_id', $line->getKey())->exists()) {
            return null;
        }

        $order = $line->order;
        $product = $line->product;

        try {
            $review = ProductReview::create([
                'site_id' => (string) $order->site_id,
                'product_id' => $product?->getKey(),
                'product_group_id' => $product?->product_group_id,
                'order_id' => $order->getKey(),
                'order_product_id' => $line->getKey(),
                'verified_buyer' => true,
                'rating' => max(1, min(5, (int) $data['rating'])),
                'title' => self::clean($data['title'] ?? null, 120),
                'body' => self::clean($data['body'] ?? null, 5000),
                'name' => self::clean($data['name'], 80) ?: (string) $order->first_name,
                'email' => (string) $order->email,
                'locale' => $order->locale ?: app()->getLocale(),
                'status' => ProductReview::STATUS_PENDING,
            ]);
        } catch (QueryException) {
            return null;
        }

        AdminNotifier::notify(
            __('Nieuwe productreview (:sterren sterren)', ['sterren' => $review->rating]),
            __(':naam schreef een review over :product. Keur hem goed of af voordat hij op de site komt.', [
                'naam' => $review->name,
                'product' => (string) $line->name,
            ]),
            rescue(fn () => ProductReviewResource::getUrl('edit', ['record' => $review]), null, false),
        );

        return $review;
    }

    public static function clean(?string $value, int $max): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
