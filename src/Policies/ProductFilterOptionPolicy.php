<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductFilterOptionPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ProductFilterOption';
    }
}
