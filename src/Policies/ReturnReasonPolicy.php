<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ReturnReasonPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ReturnReason';
    }
}
