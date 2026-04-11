<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;

class RemainderPaymentController
{
    public function __invoke(Request $request, string $orderHash)
    {
        $order = Order::where('hash', $orderHash)->firstOrFail();

        if ($order->outstandingAmount() <= 0) {
            return response()->view('dashed-ecommerce-core::remainder-payment.already-paid');
        }

        $providerId = null;
        $providerClass = null;
        foreach (ecommerce()->builder('paymentServiceProviders') as $pspId => $psp) {
            if ($psp['class']::isConnected()) {
                $providerId = $pspId;
                $providerClass = $psp['class'];
                break;
            }
        }

        if (! $providerClass) {
            return response()->view('dashed-ecommerce-core::remainder-payment.no-provider');
        }

        $orderPayment = new OrderPayment();
        $orderPayment->order_id = $order->id;
        $orderPayment->amount = $order->outstandingAmount();
        $orderPayment->status = 'pending';
        $orderPayment->psp = $providerId;
        $orderPayment->hash = (string) Str::uuid();
        $orderPayment->save();
        $orderPayment->refresh();

        try {
            $transaction = $providerClass::startTransaction($orderPayment);
        } catch (\Throwable $e) {
            return response()->view('dashed-ecommerce-core::remainder-payment.no-provider');
        }

        if (empty($transaction['redirectUrl'])) {
            return response()->view('dashed-ecommerce-core::remainder-payment.no-provider');
        }

        return redirect()->away($transaction['redirectUrl']);
    }
}
