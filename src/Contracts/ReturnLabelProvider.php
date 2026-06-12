<?php

namespace Dashed\DashedEcommerceCore\Contracts;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

interface ReturnLabelProvider
{
    /**
     * Handelt deze provider de retour van deze order af (op basis van de
     * gebruikte verzendmethode)?
     */
    public function appliesTo(Order $order): bool;

    /**
     * Genereert het retourlabel en mailt het naar de klant. Geeft true bij
     * succes. Schrijft bij succes return_label_provider/return_label_path op
     * de OrderReturn.
     */
    public function createAndSendReturnLabel(OrderReturn $orderReturn): bool;
}
