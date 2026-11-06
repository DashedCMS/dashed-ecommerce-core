<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Jobs\PrintReceiptJob;
use Dashed\DashedEcommerceCore\Jobs\OpenCashRegisterJob;

class POSHelper
{
    /**
     * De fulfilmentstatus voor een bestelling die in de kassa (of als
     * handmatige bestelling in het CMS) betaald is. Standaard Afgehandeld,
     * want de klant neemt de goederen mee; een winkel kiest bij Instellingen,
     * POS een andere status. Zonder keuze geldt de oude schakelaar
     * pos_auto_handle_orders nog, zodat een winkel die hem uit had gezet niet
     * bij het bijwerken op Afgehandeld terechtkomt. Een status die niet meer
     * bestaat telt als geen keuze.
     */
    public static function fulfillmentStatusAfterPayment(): string
    {
        $status = Customsetting::get('pos_fulfillment_status_after_payment');

        if (is_string($status) && array_key_exists($status, Orders::getFulfillmentStatusses())) {
            return $status;
        }

        return Customsetting::get('pos_auto_handle_orders', null, true) ? 'handled' : 'unhandled';
    }

    public static function finishPaidOrder(Order $order, POSCart $posCart, string $orderStatus = 'paid', ?string $fulfillmentStatus = null): array
    {
        $fulfillmentStatus ??= self::fulfillmentStatusAfterPayment();

        $order->refresh();

        if ($order->pos_order_handled) {
            return [
                'success' => true,
            ];
        }

        $order->pos_order_handled = 1;
        $order->save();

        $order->changeStatus($orderStatus);
        $order->changeFulfillmentStatus($fulfillmentStatus);

        if (Customsetting::get('pos_auto_print_receipt', null, true)) {
            // Geen sync printer-I/O meer: dispatch naar de queue zodat de
            // POS-request direct terugkomt. Een redis worker print de bon
            // binnen ~1s.
            PrintReceiptJob::dispatch($order);
        }

        $hasCashPayment = false;
        foreach ($order->orderPayments as $orderPayment) {
            // Cadeaubon-betalingen hebben geen payment_method_id (psp='giftcard');
            // null-safe nodig zodat finalisatie niet crasht bij giftcard-betaling.
            if ($orderPayment->paymentMethod?->is_cash_payment) {
                $hasCashPayment = true;
            }
        }

        $posCart->status = 'finished';
        $posCart->save();

        if ($hasCashPayment) {
            OpenCashRegisterJob::dispatch();
        }

        return [
            'success' => true,
        ];
    }
}
