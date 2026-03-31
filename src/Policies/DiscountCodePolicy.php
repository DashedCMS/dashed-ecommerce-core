<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class DiscountCodePolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'DiscountCode';
    }
}
