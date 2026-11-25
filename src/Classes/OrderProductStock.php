<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Current stock of an order line's product, for the stock badge in the order view and the mobile API.
 */
class OrderProductStock
{
    /**
     * Pieces of the product that are sellable from stock right now (stock minus reserved), or null when the
     * product does not track stock (no stock management, "always in stock", a bundle, or no product).
     */
    public static function currentQuantity(OrderProduct $orderProduct): ?int
    {
        $product = $orderProduct->product;

        if (! $product || $product->is_bundle || ! $product->use_stock) {
            return null;
        }

        return max(0, (int) $product->directSellableStock());
    }

    /**
     * Label of the stock badge: "Op voorraad (12)" when the product tracks stock, otherwise "Op voorraad".
     * Null when the line was not in stock at order time (or unknown).
     */
    public static function badgeLabel(OrderProduct $orderProduct): ?string
    {
        if ($orderProduct->stock_status_at_order !== OrderProduct::STOCK_STATUS_IN_STOCK) {
            return null;
        }

        $quantity = self::currentQuantity($orderProduct);

        return $quantity === null ? 'Op voorraad' : 'Op voorraad (' . $quantity . ')';
    }
}
