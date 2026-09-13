<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

use Throwable;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnProcessedMail;

/**
 * Verwerkt een goedgekeurde retour tot een creditorder. De creditorder zelf
 * komt uit Order::markAsCancelledWithCredit(), de enige plek die negatieve
 * regels, btw en korting uitrekent; deze klasse zet daar alleen de
 * retourkoppeling omheen. Terugbetalen is een aparte stap (RefundRegistrar).
 */
class ReturnProcessor
{
    /**
     * @param  array<int, array{order_return_line_id: int, quantity: int}>  $lines  aantal 0 = niet crediteren
     * @param  array{restock?: bool, refund_discount?: bool, note?: string|null}  $options
     */
    public function process(OrderReturn $return, array $lines, array $options = []): Order
    {
        $restock = (bool) ($options['restock'] ?? true);
        $refundDiscount = (bool) ($options['refund_discount'] ?? false);
        $note = trim((string) ($options['note'] ?? ''));

        $creditOrder = DB::transaction(function () use ($return, $lines, $restock, $refundDiscount, $note) {
            // Lock op de retourregel en statuscheck binnen de lock: twee
            // beheerders die tegelijk verwerken leveren nooit twee creditorders op.
            $locked = OrderReturn::query()->whereKey($return->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== OrderReturn::STATUS_APPROVED) {
                throw new InvalidArgumentException(__('Alleen een goedgekeurde retour kan verwerkt worden.'));
            }

            $order = $locked->order;
            if (! $order) {
                throw new InvalidArgumentException(__('De bestelling van deze retour bestaat niet meer.'));
            }

            $returnLines = $locked->lines()->with('orderProduct')->get()->keyBy('id');
            $quantities = [];
            foreach ($lines as $line) {
                $lineId = (int) ($line['order_return_line_id'] ?? 0);
                $quantity = (int) ($line['quantity'] ?? 0);
                $returnLine = $returnLines->get($lineId);

                if (! $returnLine || ! $returnLine->orderProduct) {
                    throw new InvalidArgumentException(__('Regel :id hoort niet bij deze retour.', ['id' => $lineId]));
                }
                if ($quantity < 0) {
                    throw new InvalidArgumentException(__('Een aantal kan niet negatief zijn.'));
                }

                $quantities[$lineId] = ($quantities[$lineId] ?? 0) + $quantity;
                $remaining = ReturnableLines::remaining($returnLine->orderProduct);
                if ($quantities[$lineId] > $remaining) {
                    throw new InvalidArgumentException(__('Voor :naam kan nog maar :aantal terug.', ['naam' => $returnLine->orderProduct->name, 'aantal' => $remaining]));
                }
            }

            if (array_sum($quantities) < 1) {
                throw new InvalidArgumentException(__('Geef minstens één regel een aantal boven nul, of sluit de retour zonder creditering.'));
            }

            $chosen = [];
            foreach ($quantities as $lineId => $quantity) {
                if ($quantity < 1) {
                    continue;
                }
                /** @var OrderProduct $orderProduct */
                $orderProduct = $returnLines[$lineId]->orderProduct;
                // markAsCancelledWithCredit() leest refundQuantity via array-access
                // op het model; het is geen kolom, dus dit model nooit meer save()-en.
                $orderProduct->refundQuantity = $quantity;
                $chosen[] = $orderProduct;
            }

            $creditOrder = $order->markAsCancelledWithCredit(
                sendCustomerEmail: false,
                productsMustBeReturned: false,
                restock: $restock,
                refundDiscountCosts: $refundDiscount,
                extraOrderLineName: '',
                extraOrderLinePrice: 0,
                chosenOrderProducts: $chosen,
                fulfillmentStatus: $order->fulfillment_status,
                paymentMethodId: null,
                sendAdminEmail: false,
                refillGiftcard: true,
            );

            foreach ($quantities as $lineId => $quantity) {
                $returnLines[$lineId]->update(['processed_quantity' => $quantity]);
                if ($quantity > 0) {
                    OrderProduct::withoutEvents(fn () => OrderProduct::query()
                        ->whereKey($returnLines[$lineId]->order_product_id)
                        ->increment('returned_quantity', $quantity));
                }
            }

            $locked->credit_order_id = $creditOrder->id;
            $locked->status = OrderReturn::STATUS_HANDLED;
            $locked->processed_at = now();
            $locked->handled_at = now();
            if ($note !== '') {
                $locked->admin_note = trim(($locked->admin_note ? $locked->admin_note . "\n" : '') . $note);
            }
            $locked->save();

            $order->retour_status = 'handled';
            $order->save();

            OrderLog::createLog(
                orderId: $order->id,
                tag: 'order.return-processed',
                note: __('Creditorder :nummer aangemaakt', ['nummer' => $creditOrder->invoice_id]),
            );

            return $creditOrder;
        });

        $return->refresh();
        $return->load(['order', 'lines.orderProduct', 'lines.returnReason', 'creditOrder']);

        $this->mailCustomer($return);

        return $creditOrder;
    }

    protected function mailCustomer(OrderReturn $return): void
    {
        if ($return->order?->order_origin === 'Bol') {
            OrderLog::createLog(orderId: $return->order_id, tag: 'order.return-mail-skipped-bol');

            return;
        }

        try {
            Mail::to($return->email)->queue(new OrderReturnProcessedMail($return));
        } catch (Throwable $e) {
            OrderLog::createLog(orderId: $return->order_id, tag: 'order.return-processed.mail.failed', note: 'Error: ' . $e->getMessage());
        }
    }
}
