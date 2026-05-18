<?php

namespace Dashed\DashedEcommerceCore\Mail\FulfillmentStatus;

class FulfillmentStatusReadyForPickupMail extends FulfillmentStatusChangedBaseMail
{
    public static function fulfillmentStatusKey(): string
    {
        return 'ready_for_pickup';
    }
}
