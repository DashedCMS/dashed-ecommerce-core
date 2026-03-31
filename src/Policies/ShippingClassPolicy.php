<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ShippingClassPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ShippingClass';
    }
}
