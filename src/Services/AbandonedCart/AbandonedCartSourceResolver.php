<?php

namespace Dashed\DashedEcommerceCore\Services\AbandonedCart;

use InvalidArgumentException;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

class AbandonedCartSourceResolver
{
    public static function for(AbandonedCartEmail $record): AbandonedCartSource
    {
        return match ($record->trigger_type) {
            'cart_with_email' => new CartAbandonedSource(
                $record->cart()->with(['items.product'])->firstOrFail(),
            ),
            'cancelled_order' => new CancelledOrderAbandonedSource(
                $record->cancelledOrder()->with(['orderProducts.product'])->firstOrFail(),
            ),
            default => throw new InvalidArgumentException("Unknown trigger_type: {$record->trigger_type}"),
        };
    }
}
