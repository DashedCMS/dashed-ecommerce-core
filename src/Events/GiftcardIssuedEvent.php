<?php

namespace Dashed\DashedEcommerceCore\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

class GiftcardIssuedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public DiscountCode $giftcard,
        public OrderProduct $orderProduct,
    ) {
    }
}
