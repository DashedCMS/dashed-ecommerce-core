<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

use Throwable;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRefundedMail;

/**
 * Registreert de terugbetaling van een verwerkte retour als negatieve,
 * betaalde betaling op de creditorder. Handmatig vanuit het scherm (psp
 * 'own'), straks automatisch vanuit Pay.nl (psp 'paynl' met transactie-id).
 *
 * Bewust geen changeStatus('paid') op de creditorder: die loopt door
 * markAsPaid(), zet de status op 'paid' (weg uit de retourbucket van de
 * omzet) en vuurt de betaald-events met printjobs af. Terugbetaald is: de
 * creditorder heeft een betaalde betaling (OrderReturn::isRefunded()).
 */
class RefundRegistrar
{
    public function register(OrderReturn $return, float $amount, string $method, string $psp = 'own', array $attributes = []): OrderPayment
    {
        $payment = DB::transaction(function () use ($return, $amount, $method, $psp, $attributes) {
            $locked = OrderReturn::query()->whereKey($return->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== OrderReturn::STATUS_HANDLED || ! $locked->credit_order_id || ! $locked->creditOrder) {
                throw new InvalidArgumentException(__('Alleen een verwerkte retour met creditorder kan terugbetaald worden.'));
            }

            $creditOrder = $locked->creditOrder;
            if ($creditOrder->orderPayments()->where('status', 'paid')->exists()) {
                throw new InvalidArgumentException(__('Deze retour is al terugbetaald.'));
            }

            // Na een verrekening met een factuur op rekening alleen de rest.
            $max = $locked->refundableAmount();
            $amount = round($amount, 2);
            if ($amount <= 0 || $amount > $max + 0.001) {
                throw new InvalidArgumentException(__('Het bedrag moet tussen 0,01 en :max liggen.', ['max' => CurrencyHelper::formatPrice($max)]));
            }

            $payment = $creditOrder->orderPayments()->create([
                'status' => 'paid',
                'amount' => 0 - $amount,
                'psp' => $psp,
                'psp_id' => $attributes['psp_id'] ?? null,
                'payment_method_id' => $attributes['payment_method_id'] ?? null,
                'payment_method' => $method,
                'attributes' => array_merge(['refund' => true], Arr::except($attributes, ['psp_id', 'payment_method_id'])),
            ]);

            $note = __('Terugbetaald: :bedrag via :methode', ['bedrag' => CurrencyHelper::formatPrice($amount), 'methode' => $method]);
            OrderLog::createLog(orderId: $locked->order_id, tag: 'order.return-refunded', note: $note);
            OrderLog::createLog(orderId: $creditOrder->id, tag: 'order.return-refunded', note: $note);

            return $payment;
        });

        $return->refresh();
        $return->load(['order', 'lines.orderProduct', 'creditOrder']);

        if ($return->order?->order_origin === 'Bol') {
            OrderLog::createLog(orderId: $return->order_id, tag: 'order.return-mail-skipped-bol');
        } else {
            try {
                Mail::to($return->email)->queue(new OrderReturnRefundedMail($return));
            } catch (Throwable $e) {
                OrderLog::createLog(orderId: $return->order_id, tag: 'order.return-refunded.mail.failed', note: 'Error: ' . $e->getMessage());
            }
        }

        return $payment;
    }
}
