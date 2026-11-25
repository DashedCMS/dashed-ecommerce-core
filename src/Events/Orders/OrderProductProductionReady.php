<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Dashed\DashedCore\Models\User;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

class OrderProductProductionReady
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly OrderProduct $orderProduct, public readonly ?User $user = null)
    {
    }
}
