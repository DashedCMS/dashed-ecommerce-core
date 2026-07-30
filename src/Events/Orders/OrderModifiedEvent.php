<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

class OrderModifiedEvent
{
    use Dispatchable;

    public function __construct(
        public Order $newOrder,
        public Order $oldOrder,
    ) {
    }
}
