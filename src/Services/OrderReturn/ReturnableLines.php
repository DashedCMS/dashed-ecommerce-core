<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Classes\SKUs;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Eén plek voor "wat kan er van deze bestelling nog terug". Het portaal, de
 * beheerder die een retour aanmeldt, de verwerking en de Filament-formulieren
 * lezen allemaal hier, zodat een tweede retour nooit meer kan dan het restant.
 */
class ReturnableLines
{
    public static function remaining(OrderProduct $orderProduct): int
    {
        return max(0, (int) $orderProduct->quantity - (int) ($orderProduct->returned_quantity ?? 0));
    }

    public static function isReturnable(OrderProduct $orderProduct): bool
    {
        return ! in_array($orderProduct->sku, SKUs::nonReturnable(), true);
    }

    /**
     * Orderregels met restant boven nul, zonder verzend- en betaalkosten.
     *
     * @return Collection<int, OrderProduct>
     */
    public static function forOrder(Order $order): Collection
    {
        return $order->orderProducts()->get()
            ->filter(fn (OrderProduct $op) => self::isReturnable($op) && self::remaining($op) > 0)
            ->values();
    }
}
