<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

class ReferralPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Referral';
    }
}
