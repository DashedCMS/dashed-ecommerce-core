<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Helpers;

use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

/**
 * De balk "nog € x voor gratis verzending". De drempel en het bedrag
 * waartegen hij getoetst wordt, moeten precies zijn wat de checkout doet,
 * anders belooft de balk gratis verzending die de checkout niet geeft.
 */
class FreeShippingHelper
{
    public const CACHE_KEY = 'free-shipping-threshold';

    /**
     * De laagste drempel van een gratis-verzendmethode in een verzendzone van
     * de actieve site. Per site gecachet; ShippingMethod::saved/deleted
     * leegt de cache (zie flush()).
     */
    public function threshold(?string $siteId = null): float
    {
        $siteId = $siteId ?: Sites::getActive();

        $threshold = Cache::remember(self::CACHE_KEY . ':' . $siteId, 3600, function () use ($siteId) {
            $value = ShippingMethod::query()
                ->where('sort', 'free_delivery')
                ->whereHas('shippingZone', fn ($zone) => $zone->where(fn ($q) => $q->where('site_id', $siteId)->orWhereNull('site_id')))
                ->whereNotNull('minimum_order_value')
                ->min('minimum_order_value');

            return $value === null ? null : (float) $value;
        });

        if ($threshold !== null) {
            return (float) $threshold;
        }

        $fallback = Translation::get('free-shipping-treshold', 'cart-popup', 0, 'numeric');

        return (float) ($fallback ?: 0);
    }

    public static function flush(): void
    {
        foreach (Sites::getSites() as $site) {
            Cache::forget(self::CACHE_KEY . ':' . $site['id']);
        }
    }

    /**
     * Het bedrag waartegen de checkout de minimale orderwaarde van een
     * verzendmethode toetst: het totaal zonder verzendkosten, met de korting
     * van een code die de drempel niet hoort te verlagen teruggeteld en zonder
     * verkochte cadeaubonnen. Zelfde rekensom als
     * ShoppingCart::getAvailableShippingMethods().
     */
    public function cartOrderValue(): float
    {
        $helper = cartHelper();
        $total = $helper->getTotal() - $helper->getShippingCosts();

        $discountCode = $helper->getDiscountCode();
        if ($discountCode && ! ShoppingCart::discountLowersShippingOrderValue($discountCode)) {
            $total += $helper->getDiscount();
        }

        $total -= $helper->getGiftcardProductsTotal();

        return ShoppingCart::shippingOrderValue((float) $total, $helper->getCartItems() ?? []);
    }

    /**
     * @return array{gap: float, percentage: int, reached: bool}
     */
    public function progress(float $cartTotal): array
    {
        $threshold = $this->threshold();

        // Geen gratis verzending ingesteld: dan ook niet "Je hebt gratis
        // verzending!" tonen. gap 0 en reached false verbergt de balk.
        if ($threshold <= 0) {
            return ['gap' => 0.0, 'percentage' => 0, 'reached' => false];
        }

        if ($cartTotal >= $threshold) {
            return ['gap' => 0.0, 'percentage' => 100, 'reached' => true];
        }

        $gap = round($threshold - $cartTotal, 2);
        $percentage = (int) floor((max(0.0, $cartTotal) / $threshold) * 100);

        return ['gap' => $gap, 'percentage' => $percentage, 'reached' => false];
    }

    /**
     * @return array{gap: float, percentage: int, reached: bool}
     */
    public function progressForCart(): array
    {
        return $this->progress($this->cartOrderValue());
    }
}
