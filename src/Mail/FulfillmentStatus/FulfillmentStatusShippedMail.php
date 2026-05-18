<?php

namespace Dashed\DashedEcommerceCore\Mail\FulfillmentStatus;

class FulfillmentStatusShippedMail extends FulfillmentStatusChangedBaseMail
{
    public static function fulfillmentStatusKey(): string
    {
        return 'shipped';
    }
}
