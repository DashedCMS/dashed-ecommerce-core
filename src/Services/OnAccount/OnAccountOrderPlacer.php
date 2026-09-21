<?php

namespace Dashed\DashedEcommerceCore\Services\OnAccount;

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderPayment;

/**
 * Zet een order op rekening. De order loopt daarna de gewone weg van
 * waiting_for_confirmation (factuur, voorraad, fulfilment, boekhoudpush),
 * maar zonder de betaalde nulbetaling die own-methodes anders krijgen:
 * die maakte een openstaand bedrag onberekenbaar.
 */
class OnAccountOrderPlacer
{
    public static function place(Order $order, OrderPayment $payment, User $customer): void
    {
        $payment->amount = $order->total;
        $payment->status = 'pending';
        $payment->save();

        $order->payment_due_at = now()->addDays(OnAccountSettings::termDaysFor($customer));
        $order->save();

        OrderLog::createLog(orderId: $order->id, tag: 'order.on-account.placed');

        $order->changeStatus('waiting_for_confirmation');
    }

    /**
     * Sluit de pending placeholder af als er niets meer open staat. Een
     * directe update: changeStatus() op de betaling zou de orderstatus
     * aanraken en events vuren.
     */
    public static function settle(Order $order): void
    {
        if (! $order->payment_due_at || $order->outstandingAmount() > 0.004) {
            return;
        }

        OrderPayment::query()
            ->where('order_id', $order->id)
            ->where('psp', 'own')
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }
}
