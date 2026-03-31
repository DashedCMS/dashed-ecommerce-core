<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class OrderLogTemplatePolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'OrderLogTemplate';
    }
}
