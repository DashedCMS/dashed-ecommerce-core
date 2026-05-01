<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Helpers;

use Illuminate\Support\Facades\Cache;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

class FreeShippingHelper
{
    public function threshold(): float
    {
        $method = Cache::remember(
            'free-shipping-method',
            3600,
            fn () => ShippingMethod::where('sort', 'free_delivery')->first()
        );

        if ($method && $method->minimum_order_value !== null) {
            return (float) $method->minimum_order_value;
        }

        $fallback = Translation::get('free-shipping-treshold', 'cart-popup', 0, 'numeric');

        return (float) ($fallback ?: 0);
    }

    /**
     * @return array{gap: float, percentage: int, reached: bool}
     */
    public function progress(float $cartTotal): array
    {
        $threshold = $this->threshold();

        if ($threshold <= 0) {
            return ['gap' => 0.0, 'percentage' => 100, 'reached' => true];
        }

        if ($cartTotal >= $threshold) {
            return ['gap' => 0.0, 'percentage' => 100, 'reached' => true];
        }

        $gap = round($threshold - $cartTotal, 2);
        $percentage = (int) floor(($cartTotal / $threshold) * 100);

        return ['gap' => $gap, 'percentage' => $percentage, 'reached' => false];
    }
}
