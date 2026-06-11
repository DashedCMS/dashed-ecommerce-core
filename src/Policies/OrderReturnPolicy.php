<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class OrderReturnPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'OrderReturn';
    }
}
