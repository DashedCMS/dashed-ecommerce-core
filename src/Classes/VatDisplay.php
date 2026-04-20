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

    public static function formatLinePrice(float $inclAmount, int|float|null $vatRate, string $mode): array
    {
        $ex = self::exFromIncl($inclAmount, $vatRate);

        $formatInclPrice = \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($inclAmount);
        $formatExPrice = \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($ex);

        if ($mode === 'ex') {
            return [
                'primary' => $formatExPrice,
                'secondary' => $formatInclPrice . ' incl',
                'ex' => $ex,
                'incl' => $inclAmount,
            ];
        }

        return [
            'primary' => $formatInclPrice,
            'secondary' => $formatExPrice . ' ex',
            'ex' => $ex,
            'incl' => $inclAmount,
        ];
    }
}
