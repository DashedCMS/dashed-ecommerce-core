<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Qubiqx\QcommerceEcommerceCore\Models\Order;

class OrderVariableReplacer
{
    public static function handle(Order $order, string $message): string
    {
        $message = str_replace(':name:', $order->name, $message);

        return $message;
    }
}
