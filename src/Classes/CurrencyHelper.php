<?php

namespace Dashed\DashedEcommerceCore\Classes;

class CurrencyHelper
{
    public static function formatPrice($price)
    {
        $result = '€' . number_format($price, 2, ',', '.');
        $result = str_replace(',00', ',-', $result);

        return $result;
    }
}
