<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class PaymentMethodPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'PaymentMethod';
    }
}
