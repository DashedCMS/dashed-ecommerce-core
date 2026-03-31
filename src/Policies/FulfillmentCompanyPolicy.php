<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class FulfillmentCompanyPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'FulfillmentCompany';
    }
}
