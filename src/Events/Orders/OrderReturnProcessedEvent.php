<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

/** Een retour is verwerkt en heeft een creditorder gekregen (ReturnProcessor, na de commit). */
class OrderReturnProcessedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public OrderReturn $orderReturn, public Order $creditOrder)
    {
    }
}
