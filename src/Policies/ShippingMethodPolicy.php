<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ShippingMethodPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ShippingMethod';
    }
}
