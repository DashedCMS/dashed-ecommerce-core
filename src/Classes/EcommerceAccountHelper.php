<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Illuminate\Support\Facades\Auth;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class EcommerceAccountHelper
{
    public static function getAccountOrdersUrl()
    {
        if (Auth::check()) {
            return LaravelLocalization::localizeUrl(route('qcommerce.frontend.account.orders'));
        } else {
            return LaravelLocalization::localizeUrl(route('qcommerce.frontend.auth.login'));
        }
    }
}
