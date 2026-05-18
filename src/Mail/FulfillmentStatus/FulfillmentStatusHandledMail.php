<?php

namespace Dashed\DashedEcommerceCore\Mail\FulfillmentStatus;

class FulfillmentStatusHandledMail extends FulfillmentStatusChangedBaseMail
{
    public static function fulfillmentStatusKey(): string
    {
        return 'handled';
    }
}
