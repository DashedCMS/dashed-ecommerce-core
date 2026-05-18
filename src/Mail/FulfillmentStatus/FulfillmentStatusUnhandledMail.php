<?php

namespace Dashed\DashedEcommerceCore\Mail\FulfillmentStatus;

class FulfillmentStatusUnhandledMail extends FulfillmentStatusChangedBaseMail
{
    public static function fulfillmentStatusKey(): string
    {
        return 'unhandled';
    }
}
