<?php

use Dashed\DashedEcommerceCore\EcommerceManager;

if (! function_exists('ecommerce')) {
    function ecommerce(): EcommerceManager
    {
        return app(EcommerceManager::class);
    }
}
