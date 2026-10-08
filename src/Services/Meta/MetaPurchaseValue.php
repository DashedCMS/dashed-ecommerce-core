<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Dashed\DashedEcommerceCore\Models\Order;

/**
 * De waarde en de inhoud van een aankoop zoals Meta die krijgt, voor de
 * server én de browserpixel, zodat beide hetzelfde melden.
 */
class MetaPurchaseValue
{
    public static function for(Order $order, string $valueMode): float
    {
        $total = (float) $order->total;

        $value = match ($valueMode) {
            MetaCapiSettings::VALUE_EXCL_VAT => $total - (float) $order->btw,
            MetaCapiSettings::VALUE_EXCL_VAT_EXCL_SHIPPING => $total - (float) $order->btw - static::shippingExVat($order),
            default => $total,
        };

        return round(max(0, $value), 2);
    }

    /**
     * Productregels met een prijs. Kostenregels (geen product_id) en
     * bundelcomponenten van € 0 vallen af. Het id is het product-ID, gelijk
     * aan wat de pixel bij ViewContent en AddToCart stuurt.
     *
     * @return array<int, array{id: string, quantity: int, item_price: float}>
     */
    public static function contents(Order $order): array
    {
        $contents = [];

        foreach ($order->orderProducts as $line) {
            $quantity = max(1, (int) $line->quantity);
            if (! $line->product_id || (float) $line->price <= 0) {
                continue;
            }

            $contents[] = [
                'id' => (string) $line->product_id,
                'quantity' => $quantity,
                // price is het regeltotaal na korting, niet de stukprijs.
                'item_price' => round((float) $line->price / $quantity, 2),
            ];
        }

        return $contents;
    }

    protected static function shippingExVat(Order $order): float
    {
        return (float) $order->orderProducts
            ->filter(fn ($line) => ! $line->product_id && $line->sku === 'shipping_costs')
            ->sum(fn ($line) => (float) $line->price - (float) $line->btw);
    }
}
