<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ShippingZonePolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ShippingZone';
    }
}
