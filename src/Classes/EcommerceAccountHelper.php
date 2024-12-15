<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Facades\Auth;

class EcommerceAccountHelper
{
    public static function getAccountOrdersUrl()
    {
        if (Auth::check()) {
            return route('dashed.frontend.account.orders');
        } else {
            return route('dashed.frontend.auth.login');
        }
    }
}
