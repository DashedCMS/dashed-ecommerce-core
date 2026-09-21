<?php

namespace Dashed\DashedEcommerceCore\Events\PriceGroups;

use Illuminate\Foundation\Events\Dispatchable;

class PriceGroupPricesUpdatedEvent
{
    use Dispatchable;

    public function __construct(public int $priceGroupId)
    {
    }
}
