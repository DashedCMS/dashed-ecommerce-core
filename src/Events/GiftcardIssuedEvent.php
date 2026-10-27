<?php

namespace Dashed\DashedEcommerceCore\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

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
