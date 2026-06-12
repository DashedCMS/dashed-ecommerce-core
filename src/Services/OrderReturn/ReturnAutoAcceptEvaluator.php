<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

class ReturnAutoAcceptEvaluator
{
    public function shouldAutoAccept(OrderReturn $orderReturn): bool
    {
        try {
            if (! (bool) Customsetting::get('returns_auto_accept_enabled')) {
                return false;
            }

            $order = $orderReturn->order;
            if (! $order) {
                return false;
            }

            $maxDays = (int) (Customsetting::get('returns_auto_accept_max_days') ?: 14);
            if ($order->created_at && $order->created_at->diffInDays(now()) > $maxDays) {
                return false;
            }

            $excludedOrigins = (array) (Customsetting::get('returns_auto_accept_excluded_order_origins') ?: []);
            if (in_array($order->order_origin, $excludedOrigins, true)) {
                return false;
            }

            $excludedCategoryIds = array_map('intval', (array) (Customsetting::get('returns_auto_accept_excluded_category_ids') ?: []));
            if ($excludedCategoryIds && $this->containsExcludedCategory($orderReturn, $excludedCategoryIds)) {
                return false;
            }

            $maxAmount = Customsetting::get('returns_auto_accept_max_amount');
            if ($maxAmount !== null && $maxAmount !== '' && $this->returnAmount($orderReturn) > (float) $maxAmount) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Compute the total refund amount for this return.
     *
     * OrderProduct.price is a LINE total (price for all qty items combined),
     * not a unit price. Unit price = price / quantity. We multiply unit price
     * by the returned quantity to get the refund amount per line.
     */
    protected function returnAmount(OrderReturn $orderReturn): float
    {
        return (float) $orderReturn->lines->sum(function ($line) {
            $op = $line->orderProduct;
            if (! $op) {
                return 0.0;
            }
            $orderedQty = (int) ($op->quantity ?: 1);
            // price is a LINE total, so derive unit price first
            $unitPrice = $orderedQty > 0 ? ((float) $op->price) / $orderedQty : (float) $op->price;

            return $unitPrice * (int) $line->quantity;
        });
    }

    /**
     * Check whether any returned product belongs to an excluded category.
     *
     * Uses Product::productCategories() — belongsToMany(ProductCategory::class, 'dashed__product_category').
     * ProductCategory table is 'dashed__product_categories'.
     */
    protected function containsExcludedCategory(OrderReturn $orderReturn, array $excludedCategoryIds): bool
    {
        foreach ($orderReturn->lines as $line) {
            $product = $line->orderProduct?->product;
            if (! $product) {
                continue;
            }
            $categoryIds = $product->productCategories()->pluck('dashed__product_categories.id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if (array_intersect($categoryIds, $excludedCategoryIds)) {
                return true;
            }
        }

        return false;
    }
}
