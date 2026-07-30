<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Order;

class OrderModifiedEvent
{
    use Dispatchable;

    public function __construct(
        public Order $newOrder,
        public Order $oldOrder,
    ) {
    }
}
