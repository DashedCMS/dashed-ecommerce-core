<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;
use Dashed\DashedEcommerceCore\Services\Shipping\PosShippingAdvisor;

class ProformaOrderService
{
    public static function createAndSend(POSCart $posCart, User $cashier, bool $allowShipping = false): Order
    {
        // Verzendkosten over de regels van de kassa, en dus vóór saveAsConcept:
        // die maakt de kassabon leeg. Dezelfde som als die de kassa bij het
        // kiezen toonde.
        $posShippingMethod = $posCart->shipping_method_id ? ShippingMethod::find($posCart->shipping_method_id) : null;
        $posShippingCosts = $posShippingMethod ? PosShippingAdvisor::costsFor($posCart, $posShippingMethod) : 0.0;

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
            $shippingCosts = $posShippingCosts;
        }

        $shippingVatPerRate = [];

        if ($shippingCosts > 0) {
            // Verzendkosten-btw naar verhouding van de omzet per tarief, net als
            // in de kassa zelf (zie ShippingVat). Kassaprijzen zijn incl. btw.
            $shippingVatPerRate = ShippingVat::split(
                (float) $shippingCosts,
                ShippingVat::amountPerRateFromOrderProducts($order->orderProducts()->get()),
                true
            );

            $order->subtotal = round((float) $order->subtotal + $shippingCosts, 2);
            $order->total = round((float) $order->total + $shippingCosts, 2);

            $vatPercentages = (array) ($order->vat_percentages ?? []);
            foreach ($shippingVatPerRate as $rate => $vat) {
                $vatPercentages[$rate] = round((float) ($vatPercentages[$rate] ?? 0) + $vat, 2);
            }
            $order->vat_percentages = $vatPercentages;
            $order->btw = round(array_sum($vatPercentages), 2);
        }

        $order->save();

        if ($shippingCosts > 0) {
            $shippingLine = new OrderProduct();
            $shippingLine->quantity = 1;
            $shippingLine->product_id = null;
            $shippingLine->order_id = $order->id;
            $shippingLine->name = $shippingMethod->name ?? 'Verzendkosten';
            $shippingLine->price = $shippingCosts;
            $shippingLine->btw = round(array_sum($shippingVatPerRate), 2);
            $shippingLine->vat_rate = ShippingVat::effectiveRate((float) $shippingCosts, $shippingVatPerRate, true);
            $shippingLine->discount = 0;
            $shippingLine->product_extras = [];
            $shippingLine->sku = 'shipping_costs';
            $shippingLine->save();
        }

        // OSS: order-btw volgt de regels, ook de verzendregel. Niet bij korting: die zit niet in de regelprijzen.
        if ((float) $order->discount <= 0) {
            OssVat::recalculateOrderVat($order);
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
