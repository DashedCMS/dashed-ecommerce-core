<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ProductReviewPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'ProductReview';
    }
}
