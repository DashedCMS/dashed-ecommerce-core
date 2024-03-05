<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Enums\CurrencyShowTypes;
use Exception;

class CurrencyHelper
{
    public static function formatPrice($amount): string
    {
        if (!$amount) {
            return '';
        }

        return CurrencyShowTypes::from(Customsetting::get('currency_format_type', null, 'type1'))->getValue($currency, $amount);
    }
}
