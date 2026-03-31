<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductFaqPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ProductFaq';
    }
}
