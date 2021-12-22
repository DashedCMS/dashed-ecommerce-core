<?php

namespace Qubiqx\QcommerceEcommerceCore\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Qubiqx\QcommerceEcommerceCore\QcommerceEcommerceCore
 */
class QcommerceEcommerceCore extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'qcommerce-ecommerce-core';
    }
}
