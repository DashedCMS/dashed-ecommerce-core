<?php

namespace Dashed\DashedEcommerceCore\Enums;

use Dashed\DashedCore\Models\Customsetting;

enum CurrencyShowTypes: string
{
    case TYPE1 = 'type1';
    case TYPE2 = 'type2';

    public function getValue($amount): string
    {
        $result = '';
        $showCurrencySymbol = Customsetting::get('show_currency_symbol', null, true);

        if ($this->value == 'type1') {
            $result = number_format($amount, 2, ',', '.');
            $result = str_replace(',00', ',-', $result);

            if ($showCurrencySymbol) {
                $result = '€' . $result;
            }

            return $result;
        } elseif ($this->value == 'type2') {
            $result = number_format($amount, 2, ',', '.');

            if ($showCurrencySymbol) {
                $result = '€' . $result;
            }

            return $result;
        }

        return $result;
    }
}
