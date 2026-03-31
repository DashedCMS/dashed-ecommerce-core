<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductCharacteristicsPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ProductCharacteristics';
    }
}
