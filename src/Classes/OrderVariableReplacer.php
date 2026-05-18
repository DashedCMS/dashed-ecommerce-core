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

    public static function handle(Order $order, string $message): string
    {
        $message = str($message)
            ->replace(':firstName:', (string) $order->first_name)
        ->replace(':lastName:', (string) $order->last_name)
        ->replace(':email:', (string) $order->email)
        ->replace(':phoneNumber:', (string) $order->phone_number)
        ->replace(':street:', (string) $order->street)
        ->replace(':houseNr:', (string) $order->house_nr)
        ->replace(':zipCode:', (string) $order->zip_code)
        ->replace(':city:', (string) $order->city)
        ->replace(':country:', (string) $order->country)
        ->replace(':companyName:', (string) $order->company_name)
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
        ->replace(':name:', (string) $order->name);

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
