<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;

class ProformaOrderService
{
    public static function createAndSend(POSCart $posCart, User $cashier, bool $allowShipping = false): Order
    {
        $order = ConceptOrderService::saveAsConcept($posCart, $cashier);

        $order->is_proforma = true;
        $order->proforma_allow_shipping = $allowShipping;
        $order->invoice_id = null;

        // Verzendkosten die in de kassa zijn gekozen meebakken in het proforma-
        // totaal, zodat de klant ze via de afrekenlink daadwerkelijk betaalt.
        // saveAsConcept bewaart alleen shipping_method_id; de kosten verrekenen we
        // hier (total/subtotal/btw) en tonen we als losse regel, exact zoals de
        // definitieve POS-order dat doet.
        $shippingMethod = $order->shipping_method_id
            ? ShippingMethod::find($order->shipping_method_id)
            : null;

        $shippingCosts = 0.0;
        if ($shippingMethod) {
            $shippingZone = $order->country ? ShoppingCart::getShippingZoneByCountry($order->country) : null;
            $shippingCosts = (float) ($shippingMethod->costsForCart($shippingZone->id ?? null) ?? 0);
        }

        if ($shippingCosts > 0) {
            $shippingVat = round($shippingCosts - ($shippingCosts / 1.21), 2);

            $order->subtotal = round((float) $order->subtotal + $shippingCosts, 2);
            $order->total = round((float) $order->total + $shippingCosts, 2);
            $order->btw = round((float) $order->btw + $shippingVat, 2);

            $vatPercentages = (array) ($order->vat_percentages ?? []);
            $vatPercentages['21'] = round((float) ($vatPercentages['21'] ?? 0) + $shippingVat, 2);
            $order->vat_percentages = $vatPercentages;
        }

        $order->save();

        if ($shippingCosts > 0) {
            $shippingLine = new OrderProduct();
            $shippingLine->quantity = 1;
            $shippingLine->product_id = null;
            $shippingLine->order_id = $order->id;
            $shippingLine->name = $shippingMethod->name ?? 'Verzendkosten';
            $shippingLine->price = $shippingCosts;
            $shippingLine->vat_rate = 21;
            $shippingLine->discount = 0;
            $shippingLine->product_extras = [];
            $shippingLine->sku = 'shipping_costs';
            $shippingLine->save();
        }

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
