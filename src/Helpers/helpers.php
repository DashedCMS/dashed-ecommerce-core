<?php

use Qubiqx\QcommerceEcommerceCore\EcommerceManager;

if (! function_exists('ecommerce')) {
    function ecommerce(): EcommerceManager
    {
        return app(EcommerceManager::class);
    }
}
