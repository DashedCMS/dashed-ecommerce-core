<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class CartPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Cart';
    }
}
