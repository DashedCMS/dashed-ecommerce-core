<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Jobs\CheckPinTerminalPaymentStatusJob;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\ReceiptPrinter\ReceiptPrinter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Mail\OrderConfirmationMail;
use Dashed\DashedEcommerceCore\Mail\PreOrderConfirmationMail;

class PinTerminal
{
    public static function startPayment(Order $order, PaymentMethod $paymentMethod): array
    {
        $order->refresh();
        $order->status = 'pending';
        $order->save();

        $orderPayment = new OrderPayment();
        $orderPayment->amount = $order->total - $order->orderPayments->where('status', 'paid')->sum('amount');
        $orderPayment->order_id = $order->id;
        $orderPayment->payment_method_id = $paymentMethod->id;
        $orderPayment->payment_method = $paymentMethod->name;
        $orderPayment->psp = $paymentMethod->pinTerminal->psp;
        $orderPayment->save();

        try {
            $transaction = ecommerce()->builder('paymentServiceProviders')[$orderPayment->psp]['class']::startTransaction($orderPayment);
            $pinTerminalError = false;
            $pinTerminalErrorMessage = null;
            $pinTerminalStatus = 'pending';
            CheckPinTerminalPaymentStatusJob::dispatch($orderPayment);

            return [
                'success' => true,
                'transaction' => $transaction,
                'orderPayment' => $orderPayment,
                'pinTerminalError' => $pinTerminalError,
                'pinTerminalErrorMessage' => $pinTerminalErrorMessage,
                'pinTerminalStatus' => $pinTerminalStatus,
            ];
        } catch (\Exception $exception) {
            $pinTerminalError = true;
            $pinTerminalErrorMessage = $exception->getMessage();
            if (str($pinTerminalErrorMessage)->contains('Terminal in use')) {
                $pinTerminalStatus = 'waiting_for_clearance';
            }

            return [
                'success' => false,
                'pinTerminalError' => $pinTerminalError,
                'pinTerminalErrorMessage' => $pinTerminalErrorMessage,
                'pinTerminalStatus' => $pinTerminalStatus ?? null,
                'message' => Translation::get('failed-to-start-payment-try-again', 'cart', 'De betaling kon niet worden gestart, probeer het nogmaals'),
            ];
        }
    }

    public static function openCashRegister(): array
    {
        try {
            $printer = new ReceiptPrinter();
            $printer->init(
                Customsetting::get('receipt_printer_connector_type'),
                Customsetting::get('receipt_printer_connector_descriptor')
            );
            $printer->openDrawer();
            $printer->close();

            return [
                'success' => true
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
