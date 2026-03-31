<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductGroupPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ProductGroup';
    }
}
