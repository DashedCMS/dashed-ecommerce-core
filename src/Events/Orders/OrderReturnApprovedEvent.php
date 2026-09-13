<?php

namespace Dashed\DashedEcommerceCore\Events\Orders;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

class OrderReturnApprovedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * $notifyCustomer is false als een beheerder de retour aanmeldt zonder
     * "Klant informeren", of bij een Bol-bestelling. De labellisteners van
     * MyParcel en Veloyd maken dan geen label en sturen geen mail.
     */
    public function __construct(public OrderReturn $orderReturn, public bool $notifyCustomer = true)
    {
    }
}
