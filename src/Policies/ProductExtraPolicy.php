<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductExtraPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ProductExtra';
    }
}
