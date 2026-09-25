<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\QuoteLine;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Bouwt de conceptorder uit een geaccepteerde offerte. Plaatsen (proforma, op
 * rekening, betaald) doet QuoteConverter, en die roept dit aan.
 *
 * De order wordt volledig aan de serverkant uit de offerteregels opgebouwd; er
 * gaat geen bedrag en geen regel uit een verzoek mee, want dan bepaalt de klant
 * de prijs.
 */
class QuoteToOrder
{
    public static function build(Quote $quote): Order
    {
        $totals = QuoteTotals::for($quote);

        $order = new Order();
        $order->quote_id = $quote->id;
        $order->user_id = $quote->user_id;
        $order->locale = $quote->locale;
        $order->prices_ex_vat = (bool) $quote->prices_ex_vat;
        $order->status = 'concept';
        $order->fulfillment_status = 'unhandled';
        $order->invoice_id = 'PROFORMA';
        $order->note = $quote->title;

        foreach ([
            'company_name', 'btw_id', 'first_name', 'last_name', 'email', 'phone_number',
            'invoice_street', 'invoice_house_nr', 'invoice_zip_code', 'invoice_city', 'invoice_country',
        ] as $field) {
            $order->{$field} = $quote->{$field};
        }

        // Het afleveradres valt terug op het factuuradres. Bij vooraf betalen
        // vult de proforma-checkout het alsnog in, maar op rekening is er geen
        // checkout: die order gaat rechtstreeks naar waiting_for_confirmation
        // met een factuur, en Order zelf kent geen terugval van factuur- naar
        // afleveradres.
        foreach ([
            'street' => 'invoice_street',
            'house_nr' => 'invoice_house_nr',
            'zip_code' => 'invoice_zip_code',
            'city' => 'invoice_city',
            'country' => 'invoice_country',
        ] as $field => $invoiceField) {
            $order->{$field} = filled($quote->{$field}) ? $quote->{$field} : $quote->{$invoiceField};
        }

        $order->subtotal = $totals->subtotal;
        $order->btw = $totals->vat;
        $order->total = $totals->total;
        $order->discount = 0;
        $order->vat_percentages = $totals->vatPerRate;
        $order->save();

        // Order::boot()'s creating-hook zet locale altijd op app()->getLocale()
        // en site_id altijd op de actieve site, dus een toewijzing hierboven
        // overleeft die eerste save() niet vanzelf. QuoteConverter zet de
        // apptaal wel gelijk aan de offerte voordat build() draait, maar een
        // aanroep die dat niet doet hoort niet stil de verkeerde taal te
        // krijgen. De site is nooit goed te raden: een omzetting uit de
        // wachtrij of van de console heeft geen actieve site, en een klant die
        // de link op een zustersite opent zou zijn order daar laten landen. Dat
        // verschuift ook welke betaalmethode op rekening gevonden wordt, want
        // die zoekopdracht in QuoteConverter filtert op site_id.
        if ($order->locale !== $quote->locale || (string) $order->site_id !== (string) $quote->site_id) {
            $order->locale = $quote->locale;
            $order->site_id = $quote->site_id;
            $order->save();
        }

        foreach ($quote->selectedLines() as $line) {
            self::createOrderProduct($order, $line);
        }

        OrderLog::createLog(orderId: $order->id, tag: 'order.created-from-quote', note: $quote->displayNumber());

        return $order->fresh();
    }

    private static function createOrderProduct(Order $order, QuoteLine $line): void
    {
        $orderProduct = new OrderProduct();
        $orderProduct->order_id = $order->id;
        $orderProduct->product_id = $line->product_id;
        $orderProduct->name = $line->name;
        $orderProduct->sku = $line->sku;
        $orderProduct->quantity = (int) $line->quantity;
        // price is het regeltotaal inclusief btw; de creating-hook van
        // OrderProduct leidt btw af uit price en vat_rate.
        $orderProduct->price = $line->lineTotal();
        $orderProduct->vat_rate = (float) $line->vat_rate;
        $orderProduct->discount = 0;
        $orderProduct->product_extras = [];
        $orderProduct->save();
    }
}
