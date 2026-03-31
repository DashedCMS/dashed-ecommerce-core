<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductTabPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ProductTab';
    }
}
