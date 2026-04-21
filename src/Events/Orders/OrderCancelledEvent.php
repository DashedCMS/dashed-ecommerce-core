<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelledEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order)
    {
    }
}
