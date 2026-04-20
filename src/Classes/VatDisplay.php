<?php

namespace Dashed\DashedEcommerceCore\Classes;

class VatDisplay
{
    public static function exFromIncl(float $amount, int|float|null $vatRate): float
    {
        $rate = max(0.0, (float) ($vatRate ?? 0));

        if ($rate <= 0.0) {
            return $amount;
        }

        return $amount / (1 + $rate / 100);
    }
}
