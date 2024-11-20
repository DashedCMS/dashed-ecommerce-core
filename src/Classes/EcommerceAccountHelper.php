<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\Auth;
use Dashed\LaravelLocalization\Facades\LaravelLocalization;

class EcommerceAccountHelper
{
    public static function getAccountOrdersUrl()
    {
        if (Auth::check()) {
            return LaravelLocalization::localizeUrl(route('dashed.frontend.account.orders'));
        } else {
            return LaravelLocalization::localizeUrl(route('dashed.frontend.auth.login'));
        }
    }
}
