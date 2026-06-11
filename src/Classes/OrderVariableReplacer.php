<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedEcommerceCore\Models\Order;

class OrderVariableReplacer
{
    public static function getAvailableVariables(): array
    {
        return [
            ':name:',
            ':firstName:',
            ':lastName:',
            ':email:',
            ':phoneNumber:',
            ':street:',
            ':houseNr:',
            ':zipCode:',
            ':city:',
            ':country:',
            ':companyName:',
            ':total:',
            ':tax:',
            ':amountOfProducts:',
            ':invoiceId:',
            ':orderId:',
            ':discount:',
            ':orderOrigin:',
            ':fulfillmentStatus:',
            ':fulfillmentStatusName:',
            ':trackingCodes:',
            ':trackingLinks:',
        ];
    }

    public static function handle(Order $order, string $message, bool $escapeHtml = false): string
    {
        // Bij HTML-output (e-mailbodies) worden klant-ingevulde velden ge-escaped zodat
        // een naam/adres met <script> geen stored XSS oplevert. Standaard uit voor
        // platte-tekst-contexten (zoals onderwerpregels).
        $e = fn (string $value): string => $escapeHtml ? e($value) : $value;

        $message = str($message)
            ->replace(':firstName:', $e((string) $order->first_name))
        ->replace(':lastName:', $e((string) $order->last_name))
        ->replace(':email:', $e((string) $order->email))
        ->replace(':phoneNumber:', $e((string) $order->phone_number))
        ->replace(':street:', $e((string) $order->street))
        ->replace(':houseNr:', $e((string) $order->house_nr))
        ->replace(':zipCode:', $e((string) $order->zip_code))
        ->replace(':city:', $e((string) $order->city))
        ->replace(':country:', $e((string) $order->country))
        ->replace(':companyName:', $e((string) $order->company_name))
        ->replace(':total:', CurrencyHelper::formatPrice($order->total))
        ->replace(':tax:', CurrencyHelper::formatPrice($order->tax))
        ->replace(':amountOfProducts:', (string) $order->amount_of_products)
        ->replace(':invoiceId:', (string) $order->invoice_id)
        ->replace(':orderId:', (string) $order->id)
        ->replace(':discount:', CurrencyHelper::formatPrice($order->discount))
        ->replace(':orderOrigin:', $order->order_origin ?? 'N/A')
        ->replace(':fulfillmentStatus:', (string) $order->fulfillment_status)
        ->replace(':fulfillmentStatusName:', Orders::getFulfillmentStatusses()[$order->fulfillment_status] ?? (string) $order->fulfillment_status)
        ->replace(':trackingCodes:', self::trackingCodes($order))
        ->replace(':trackingLinks:', self::trackingLinks($order))
        ->replace(':name:', $e((string) $order->name));

        return $message;
    }

    protected static function trackingCodes(Order $order): string
    {
        return $order->trackAndTraces
            ->pluck('code')
            ->filter()
            ->unique()
            ->implode(', ');
    }

    protected static function trackingLinks(Order $order): string
    {
        return $order->trackAndTraces
            ->pluck('url')
            ->filter()
            ->unique()
            ->implode(', ');
    }
}
