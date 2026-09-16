<?php

namespace Dashed\DashedEcommerceCore\Services\AbandonedCart;

use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

class AbandonedCartSourceResolver
{
    public static function for(AbandonedCartEmail $record): AbandonedCartSource
    {
        return AbandonedCartTriggers::resolve($record);
    }
}
