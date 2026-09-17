<?php

namespace Dashed\DashedEcommerceCore\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

/**
 * Een GS1-run zet EAN's op producten, dus dezelfde rechten als producten.
 */
class Gs1RunPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Product';
    }
}
