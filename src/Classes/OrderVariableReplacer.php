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
        ];
    }

    public static function handle(Order $order, string $message): string
    {
        $message = str($message)
            ->replace(':firstName:', $order->first_name)
        ->replace(':lastName:', $order->last_name)
        ->replace(':email:', $order->email)
        ->replace(':phoneNumber:', $order->phone_number)
        ->replace(':street:', $order->street)
        ->replace(':houseNr:', $order->house_nr)
        ->replace(':zipCode:', $order->zip_code)
        ->replace(':city:', $order->city)
        ->replace(':country:', $order->country)
        ->replace(':companyName:', $order->company_name)
        ->replace(':total:', CurrencyHelper::formatPrice($order->total))
        ->replace(':tax:', CurrencyHelper::formatPrice($order->tax))
        ->replace(':amountOfProducts:', $order->amount_of_products)
        ->replace(':invoiceId:', $order->invoice_id)
        ->replace(':orderId:', $order->id)
        ->replace(':discount:', CurrencyHelper::formatPrice($order->discount))
        ->replace(':orderOrigin:', $order->order_origin ?? 'N/A')
        ->replace(':name:', $order->name);

        return $message;
    }
}
