<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Mail\OrderConfirmationMail;
use Dashed\DashedEcommerceCore\Mail\PreOrderConfirmationMail;

class POSHelper
{
    public static function finishPaidOrder(Order $order, POSCart $posCart): array
    {
        $order->changeStatus('paid');
        $order->changeFulfillmentStatus('handled');

        try {
            $order->printReceipt();
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
