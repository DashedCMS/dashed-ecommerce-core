<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Illuminate\Support\Facades\Request;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Models\ProductCategory;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

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
