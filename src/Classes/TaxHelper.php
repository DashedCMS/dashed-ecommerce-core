<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\Customsetting;

class TaxHelper
{
    public static function calculateTax($price, $taxPercentage)
    {
        $calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes');
        if ($calculateInclusiveTax) {
            return ($price / (100 + $taxPercentage) * $taxPercentage);
        } else {
            return ($price / 100 * $taxPercentage);
        }
    }
}
