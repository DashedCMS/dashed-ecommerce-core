<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Models\QuoteLine;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedCore\Classes\AdminActionMonitor;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountOverride;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountOrderPlacer;

/**
 * De enige plek die van een geaccepteerde offerte een bestelling maakt. De
 * publieke akkoordknop, de handmatige actie in het CMS en een eventuele latere
 * API lopen hier doorheen.
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
            'street', 'house_nr', 'zip_code', 'city', 'country',
            'invoice_street', 'invoice_house_nr', 'invoice_zip_code', 'invoice_city', 'invoice_country',
        ] as $field) {
            $order->{$field} = $quote->{$field};
        }

        $order->subtotal = $totals->subtotal;
        $order->btw = $totals->vat;
        $order->total = $totals->total;
        $order->discount = 0;
        $order->vat_percentages = $totals->vatPerRate;
        $order->save();

        foreach ($quote->selectedLines() as $line) {
            self::createOrderProduct($order, $line);
        }

        OrderLog::createLog(orderId: $order->id, tag: 'order.created-from-quote', note: $quote->displayNumber());

        return $quote->payment_route === Quote::ROUTE_ON_ACCOUNT
            ? self::placeOnAccount($order, $quote)
            : self::placeAsProforma($order);
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

    private static function placeAsProforma(Order $order): Order
    {
        $order->is_proforma = true;
        $order->proforma_allow_shipping = true;
        $order->proforma_sent_at = now();
        $order->invoice_id = null;
        $order->save();

        return $order->fresh();
    }

    /**
     * Zegt de kredietcontrole nee, dan blijft de order concept en gaat er een
     * melding uit. De klant heeft ja gezegd en dat staat vast; een limiet die
     * op dat ene moment omvalt hoort geen deal te laten verdampen.
     */
    private static function placeOnAccount(Order $order, Quote $quote): Order
    {
        $customer = $quote->user;

        if (! $customer) {
            self::refuse($order, $quote, 'no_customer');

            return $order->fresh();
        }

        $check = OnAccountOverride::precheck($customer, (float) $order->total, (string) $order->site_id);

        if (! $check->allowed) {
            self::refuse($order, $quote, (string) $check->reason);

            return $order->fresh();
        }

        $payment = $order->orderPayments()->create([
            'psp' => 'own',
            'payment_method' => __('Op rekening'),
            'amount' => 0,
            'status' => 'pending',
        ]);

        OnAccountOrderPlacer::place($order, $payment, $customer);

        return $order->fresh();
    }

    private static function refuse(Order $order, Quote $quote, string $reason): void
    {
        OrderLog::createLog(orderId: $order->id, tag: 'order.quote-on-account-refused', note: $reason);

        rescue(fn () => AdminActionMonitor::alert(__('Offerte geaccepteerd, maar niet op rekening te zetten'), [
            __('Offerte') => $quote->displayNumber(),
            __('Bestelling') => (string) $order->id,
            __('Klant') => (string) $quote->email,
            __('Reden') => $reason,
        ]));
    }
}
