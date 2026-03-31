<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductCategoryPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ProductCategory';
    }
}
