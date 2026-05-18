<?php

namespace Dashed\DashedEcommerceCore\Mail\FulfillmentStatus;

class FulfillmentStatusPackedMail extends FulfillmentStatusChangedBaseMail
{
    public static function fulfillmentStatusKey(): string
    {
        return 'packed';
    }
}
