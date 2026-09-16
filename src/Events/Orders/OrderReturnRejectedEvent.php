<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

/** Een retour is afgekeurd (OrderReturn::reject()). */
class OrderReturnRejectedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public OrderReturn $orderReturn)
    {
    }
}
