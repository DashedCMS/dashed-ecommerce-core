<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class OrderPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Order';
    }
}
