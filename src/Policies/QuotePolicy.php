<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class QuotePolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Quote';
    }
}
