<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\VatDisplay;

/**
 * De waarde van een AddToCart, InitiateCheckout of ViewContent volgens de
 * value mode van de Conversions API, voor de server én de browserpixel, zodat
 * beide hetzelfde bedrag melden. Bij "inclusief btw" is dat exact wat de pixel
 * altijd al stuurde.
 */
class MetaEventValue
{
    /** Stukprijs van een product volgens de value mode. */
    public static function unit(float $unitPrice, ?Product $product, string $valueMode): float
    {
        if ($valueMode === MetaCapiSettings::VALUE_INCL_VAT || ! Customsetting::get('taxes_prices_include_taxes')) {
            return round(max(0, $unitPrice), 2);
        }

        return round(max(0, VatDisplay::exFromIncl($unitPrice, (int) ($product?->vat_rate ?? 21))), 2);
    }

    /**
     * Wagentotaal volgens de value mode. De verzendkosten gaan er exclusief
     * btw af met dezelfde btw-verhouding als de rest van de wagen.
     */
    public static function cart(float $total, float $tax, float $shippingCosts, string $valueMode): float
    {
        $total = max(0, $total);
        $exVat = max(0, $total - max(0, $tax));

        $value = match ($valueMode) {
            MetaCapiSettings::VALUE_EXCL_VAT => $exVat,
            MetaCapiSettings::VALUE_EXCL_VAT_EXCL_SHIPPING => $total > 0
                ? $exVat - (min($total, max(0, $shippingCosts)) * ($exVat / $total))
                : 0,
            default => $total,
        };

        return round(max(0, $value), 2);
    }
}
