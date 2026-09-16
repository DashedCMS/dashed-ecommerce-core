<?php

namespace Dashed\DashedEcommerceCore\Services\Payments;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Events\Orders\PaymentRefundReportedEvent;

/**
 * De enige plek die de status van een betaling bij de PSP ophaalt en
 * doorzet. Drie plekken vragen dat: de klantpagina na het betalen
 * (TransactionController::complete), de exchange-webhook
 * (TransactionController::exchange) en de Livewire-orderpagina
 * (Livewire\Frontend\Orders\ViewOrder). Ze stonden alle drie los van elkaar,
 * en daardoor draaide de klantpagina de betaling bij een terugbetaling alsnog
 * naar 'refunded' terwijl de twee andere dat al niet meer deden.
 *
 * Bij 'refunded' blijft de betaling staan zoals hij is: een terugbetaling
 * hoort op de creditorder van de retour, niet op de oorspronkelijke betaling.
 * Er gaat wel altijd een orderlog op de bestelling, want dat is het enige
 * spoor voor een PSP waar niets naar PaymentRefundReportedEvent luistert
 * (vandaag Multisafepay, die net als Pay.nl 'refunded' teruggeeft).
 */
class PspStatusResolver
{
    public const TAG_REFUND_REPORTED = 'order.psp-refund-reported';

    /**
     * Haalt de status op bij de PSP van deze betaling en zet hem door.
     *
     * @param  bool  $changeOrderStatus  de exchange zet de orderstatus binnen
     *                                   de lus; de klantpagina's doen dat er
     *                                   zelf na, samen met de GA-hit.
     * @return string|null de nieuwe status van de betaling, of null als er
     *                     niets is doorgezet (onbekende PSP of terugbetaling)
     */
    public static function apply(OrderPayment $payment, Order $order, bool $changeOrderStatus): ?string
    {
        foreach (ecommerce()->builder('paymentServiceProviders') ?: [] as $pspId => $psp) {
            if ($payment->psp != $pspId) {
                continue;
            }

            $newStatus = $psp['class']::getOrderStatus($payment);

            if ($newStatus === 'refunded') {
                OrderLog::createLog(
                    orderId: $order->id,
                    tag: self::TAG_REFUND_REPORTED,
                    note: __('Terugbetaling gemeld door :psp op betaling :betaling. De betaling blijft staan; een terugbetaling hoort op de creditorder van de retour.', [
                        'psp' => $pspId,
                        'betaling' => $payment->id,
                    ]),
                );

                PaymentRefundReportedEvent::dispatch($payment);

                return null;
            }

            $newPaymentStatus = $payment->changeStatus($newStatus);

            if ($changeOrderStatus) {
                $order->changeStatus($newPaymentStatus);
            }

            return $newPaymentStatus;
        }

        return null;
    }
}
