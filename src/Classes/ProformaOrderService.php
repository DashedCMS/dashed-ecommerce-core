<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;

class ProformaOrderService
{
    public static function createAndSend(POSCart $posCart, User $cashier, bool $allowShipping = false): Order
    {
        $order = ConceptOrderService::saveAsConcept($posCart, $cashier);

        $order->is_proforma = true;
        $order->proforma_allow_shipping = $allowShipping;
        $order->invoice_id = null;
        $order->save();

        $checkoutUrl = url('/proforma/' . $order->hash);

        Mail::to($order->email)->send(new ProformaCheckoutMail($order, $checkoutUrl));

        $order->proforma_sent_at = now();
        $order->save();

        // Sluit de POS-cart af (zoals een afgeronde verkoop), zodat de volgende
        // verkoop met een verse, lege cart start en de klantgegevens/verzending
        // van deze proforma niet blijven staan.
        $posCart->status = 'finished';
        $posCart->save();

        return $order;
    }
}
