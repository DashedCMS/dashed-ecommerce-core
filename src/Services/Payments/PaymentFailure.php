<?php

namespace Dashed\DashedEcommerceCore\Services\Payments;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;

/**
 * Wat de checkout en de herstelmail moeten weten over een mislukte betaling:
 * welke order, of de betaalmethode heeft geweigerd (OrderPayment::isDeclined())
 * en welke methode dat was. Eén plek, zodat de terugkeer van de PSP
 * (ShoppingCart::cancelledPaymentRedirect), de herstel-link uit de mail
 * (OrderRecoveryController) en de mailtekst (CancelledOrderAbandonedSource)
 * hetzelfde zeggen.
 */
class PaymentFailure
{
    public const SESSION_ORDER_ID = 'cancelled_order_id';

    public const SESSION_DECLINED = 'payment_declined';

    public const SESSION_METHOD_ID = 'declined_payment_method_id';

    public static function lastPayment(Order $order): ?OrderPayment
    {
        return $order->orderPayments()->orderByDesc('id')->first();
    }

    public static function isDeclined(Order $order): bool
    {
        return (bool) self::lastPayment($order)?->isDeclined();
    }

    /**
     * @return array{cancelled_order_id: int, payment_declined: bool, declined_payment_method_id: int|null}
     */
    public static function sessionData(Order $order): array
    {
        $payment = self::lastPayment($order);
        $declined = (bool) $payment?->isDeclined();
        $methodId = $declined && $payment?->payment_method_id ? (int) $payment->payment_method_id : null;

        return [
            self::SESSION_ORDER_ID => (int) $order->id,
            self::SESSION_DECLINED => $declined,
            self::SESSION_METHOD_ID => $methodId,
        ];
    }

    public static function flash(Order $order): void
    {
        foreach (self::sessionData($order) as $key => $value) {
            session()->flash($key, $value);
        }
    }

    /**
     * Naam van de betaalmethode van de laatste betaling, in de huidige taal.
     * Oudere betalingen hebben soms alleen de tekstkolom.
     */
    public static function methodName(Order $order): string
    {
        $payment = self::lastPayment($order);

        if (! $payment) {
            return '';
        }

        $name = $payment->paymentMethod?->name;

        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        return trim((string) ($payment->payment_method ?? ''));
    }
}
