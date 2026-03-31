<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductFilterPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ProductFilter';
    }
}
