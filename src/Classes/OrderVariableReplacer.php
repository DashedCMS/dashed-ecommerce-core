<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedEcommerceCore\Models\Order;

class OrderVariableReplacer
{
    public static function handle(Order $order, string $message): string
    {
        $message = str_replace(':name:', $order->name, $message);

        return $message;
    }
}
