<?php

namespace Dashed\DashedEcommerceCore\Services\OnAccount;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderPayment;

/**
 * Een credit op een order op rekening die nog open staat, wordt verrekend
 * en niet terugbetaald: de klant heeft het geld nooit betaald. De
 * verrekening is een betaalde OrderPayment op de oorspronkelijke order met
 * psp 'credit', zodat het saldo alleen naar betalingen hoeft te kijken.
 */
class OnAccountSettlement
{
    public static function applyCredit(Order $original, Order $creditOrder): float
    {
        if (! $original->payment_due_at) {
            return 0.0;
        }

        if ((float) $creditOrder->total >= 0) {
            return 0.0;
        }

        $amount = round(min(abs((float) $creditOrder->total), $original->outstandingAmount()), 2);

        if ($amount <= 0) {
            return 0.0;
        }

        // Geen factuurnummer in het label: invoice_id staat op dit moment nog
        // op de plaatshouder 'RETURN' (generateInvoiceId() loopt pas later in
        // markAsCancelledWithCredit()), dus dat zou permanent fout blijven
        // staan. credit_order_id legt de koppeling al vast.
        //
        // Rechtstreeks als betaald opslaan: changeStatus('paid') zou de
        // oorspronkelijke order op paid of partially_paid zetten.
        OrderPayment::create([
            'order_id' => $original->id,
            'psp' => 'credit',
            'payment_method' => __('Verrekend met creditfactuur'),
            'amount' => $amount,
            'status' => 'paid',
            'credit_order_id' => $creditOrder->id,
        ]);

        OrderLog::createLog(orderId: $original->id, tag: 'order.on-account.credit-settled', note: (string) $amount);

        OnAccountOrderPlacer::settle($original->fresh());

        return $amount;
    }
}
