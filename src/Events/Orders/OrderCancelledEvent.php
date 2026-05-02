<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Illuminate\Queue\SerializesModels;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

class OrderCancelledEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order)
    {
    }
}
