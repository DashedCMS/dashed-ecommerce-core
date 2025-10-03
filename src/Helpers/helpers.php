<?php

use Dashed\DashedEcommerceCore\EcommerceManager;

if (! function_exists('ecommerce')) {
    function ecommerce(): EcommerceManager
    {
        return app(EcommerceManager::class);
    }
}

if (! function_exists('cartHelper')) {
    function cartHelper(): \Dashed\DashedEcommerceCore\Classes\CartHelper
    {
        return app(\Dashed\DashedEcommerceCore\Classes\CartHelper::class);
    }
}
