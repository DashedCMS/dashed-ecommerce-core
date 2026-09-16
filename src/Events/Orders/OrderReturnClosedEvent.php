<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

/** Een retour is gesloten zonder creditering (OrderReturn::close()). */
class OrderReturnClosedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public OrderReturn $orderReturn)
    {
    }
}
