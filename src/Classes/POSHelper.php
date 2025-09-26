<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;

class POSHelper
{
    public static function finishPaidOrder(Order $order, POSCart $posCart, string $orderStatus = 'paid', string $fulfillmentStatus = 'handled'): array
    {
        $order->changeStatus($orderStatus);
        $order->changeFulfillmentStatus($fulfillmentStatus);

        try {
//            $order->printReceipt();
        } catch (\Exception $exception) {
        }

        $hasCashPayment = false;
        foreach ($order->orderPayments as $orderPayment) {
            if ($orderPayment->paymentMethod->is_cash_payment) {
                $hasCashPayment = true;
            }
        }

        $posCart->status = 'finished';
        $posCart->save();

        if ($hasCashPayment) {
            PinTerminal::openCashRegister();
        }

        return [
            'success' => true,
        ];
    }
}
