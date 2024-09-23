<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedEcommerceCore\Models\PaymentMethod;

class PaymentMethods
{
    public static function get()
    {
        $paymentMethods = [];
        foreach (PaymentMethod::get() as $paymentMethod) {
            $paymentMethods[] = [
                'id' => $paymentMethod->id,
                'system' => 'own',
                'name' => $paymentMethod->name,
                'image' => [],
                'postpay' => false,
                'extra_costs' => $paymentMethod->extra_costs,
                'additional_info' => $paymentMethod->additional_info,
                'payment_instructions' => $paymentMethod->payment_instructions,
                'deposit_calculation' => $paymentMethod->deposit_calculation,
            ];
        }

        return $paymentMethods;
    }

    public static function getTypes()
    {
        return [
            'online' => 'Online',
            'pos' => 'Point of Sale',
        ];
    }
}
