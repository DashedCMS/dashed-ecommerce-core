<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductQuestionPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ProductQuestion';
    }
}
