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

    public static function resolveMode($cart, $user): string
    {
        if ($cart && ! is_null($cart->prices_ex_vat ?? null) && (bool) $cart->prices_ex_vat) {
            return 'ex';
        }

        if ($user && ! empty($user->show_prices_ex_vat)) {
            return 'ex';
        }

        return 'incl';
    }
}
