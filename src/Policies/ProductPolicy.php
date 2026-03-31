<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Product';
    }
}
