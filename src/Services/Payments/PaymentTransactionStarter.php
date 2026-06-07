<?php

namespace Dashed\DashedEcommerceCore\Services\Payments;

use Throwable;
use Illuminate\Support\Facades\Log;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Mail\AdminPaymentStartFailedMail;

/**
 * Centrale entry-point voor het starten van een PSP-transactie.
 *
 * Vervangt elke directe aanroep van
 * `ecommerce()->builder('paymentServiceProviders')[$psp]['class']::startTransaction(...)`.
 *
 * Bij succes wordt de transaction-array uit de provider doorgegeven.
 * Bij elke Throwable:
 *   1. Een `OrderLog` met tag `order.payment-start.failed` en de exacte
 *      foutmelding (klasse + bericht + bestand:regel).
 *   2. Een admin-notificatie via `AdminNotifier::send(...)` zodat alle
 *      admin-mail-ontvangers per e-mail en (indien geconfigureerd) per
 *      Telegram op de hoogte zijn.
 *   3. De originele exception wordt opnieuw gegooid zodat de caller
 *      zijn eigen UX-respons (terug-redirect, foutmelding tonen,
 *      response array vullen) kan afhandelen.
 *
 * `context` is een korte tag (bv. `checkout`, `pos.pin-terminal`,
 * `payment-link.retry`) die in OrderLog, mail en Telegram terugkomt
 * zodat duidelijk is waar in de applicatie de start mislukte.
 */
class PaymentTransactionStarter
{
    public const CONTEXT_CHECKOUT = 'checkout';
    public const CONTEXT_POS_PIN_TERMINAL = 'pos.pin-terminal';
    public const CONTEXT_PAYMENT_LINK_RETRY = 'payment-link.retry';
    public const CONTEXT_REMAINDER_PAYMENT = 'remainder-payment';
    public const CONTEXT_MANUAL_ORDER = 'admin.manual-order';

    /**
     * @return array<string, mixed>  Provider transaction payload (bevat o.a. `redirectUrl`)
     *
     * @throws Throwable Originele exception uit de PSP, na logging + notificatie.
     */
    public static function start(OrderPayment $orderPayment, string $context = 'general'): array
    {
        $providers = ecommerce()->builder('paymentServiceProviders');
        $providerEntry = $providers[$orderPayment->psp] ?? null;
        if (! $providerEntry || empty($providerEntry['class'])) {
            $e = new \RuntimeException("Geen payment provider geregistreerd voor PSP '{$orderPayment->psp}'.");
            self::handleFailure($orderPayment, $context, $e);

            throw $e;
        }

        try {
            return $providerEntry['class']::startTransaction($orderPayment);
        } catch (Throwable $e) {
            self::handleFailure($orderPayment, $context, $e);

            throw $e;
        }
    }

    private static function handleFailure(OrderPayment $orderPayment, string $context, Throwable $e): void
    {
        $order = $orderPayment->order;

        if ($order) {
            $note = sprintf(
                '[%s] %s: %s (in %s:%d)',
                $context,
                self::shortClassName($e),
                $e->getMessage(),
                basename($e->getFile()),
                $e->getLine(),
            );

            try {
                OrderLog::createLog(
                    orderId: $order->id,
                    tag: 'order.payment-start.failed',
                    note: $note,
                );
            } catch (Throwable $logError) {
                Log::warning('PaymentTransactionStarter could not write OrderLog', [
                    'order_id' => $order->id,
                    'error' => $logError->getMessage(),
                ]);
            }

            try {
                AdminNotifier::send(
                    new AdminPaymentStartFailedMail($order, $orderPayment, $e, $context),
                    Mails::getAdminNotificationEmails(),
                );
            } catch (Throwable $notifyError) {
                Log::warning('PaymentTransactionStarter could not notify admins', [
                    'order_id' => $order->id,
                    'error' => $notifyError->getMessage(),
                ]);
            }
        } else {
            Log::warning('PaymentTransactionStarter: OrderPayment has no order', [
                'order_payment_id' => $orderPayment->id ?? null,
                'psp' => $orderPayment->psp,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private static function shortClassName(Throwable $e): string
    {
        $segments = explode('\\', $e::class);

        return end($segments) ?: $e::class;
    }
}
