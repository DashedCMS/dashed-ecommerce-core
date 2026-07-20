<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

class OrderReturnRequestedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public OrderReturn $orderReturn)
    {
    }
}
