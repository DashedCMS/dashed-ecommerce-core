<?php

namespace Dashed\DashedEcommerceCore\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dashed\DashedEcommerceCore\DashedEcommerceCore
 */
class DashedEcommerceCore extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dashed-ecommerce-core';
    }
}
