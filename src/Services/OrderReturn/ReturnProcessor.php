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
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnProcessedEvent;
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
     * @param  array{restock?: bool, refund_discount?: bool, note?: string|null, confirm_existing_credit?: bool}  $options
     *
     * @throws ExistingCreditOrderException als de bestelling al een creditorder heeft en confirm_existing_credit niet aan staat
     */
    public function process(OrderReturn $return, array $lines, array $options = []): Order
    {
        $restock = (bool) ($options['restock'] ?? true);
        $refundDiscount = (bool) ($options['refund_discount'] ?? false);
        $note = trim((string) ($options['note'] ?? ''));
        $confirmExistingCredit = (bool) ($options['confirm_existing_credit'] ?? false);

        $creditOrder = DB::transaction(function () use ($return, $lines, $restock, $refundDiscount, $note, $confirmExistingCredit) {
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

            // Binnen de lock, zodat een creditorder die net door een andere
            // verwerking is aangemaakt ook meetelt.
            if (! $confirmExistingCredit) {
                $existing = self::existingCreditOrders($order);
                if ($existing->isNotEmpty()) {
                    throw new ExistingCreditOrderException($existing);
                }
            }

            $returnLines = $locked->lines()->with('orderProduct')->get()->keyBy('id');
            $quantities = [];
            $perOrderProduct = [];
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
                // Het restant hangt aan de orderregel en niet aan de retourregel.
                // Twee retourregels die naar hetzelfde orderproduct wijzen (Bol en
                // Pay.nl leveren dat straks aan) moeten dus bij elkaar opgeteld
                // tegen het restant gehouden worden, anders komt er meer terug dan
                // er besteld is.
                $orderProductId = (int) $returnLine->order_product_id;
                $perOrderProduct[$orderProductId] = ($perOrderProduct[$orderProductId] ?? 0) + $quantity;
                $remaining = ReturnableLines::remaining($returnLine->orderProduct);
                if ($perOrderProduct[$orderProductId] > $remaining) {
                    throw new InvalidArgumentException(__('Voor :naam kan nog maar :aantal terug.', ['naam' => $returnLine->orderProduct->name, 'aantal' => $remaining]));
                }
            }

            if (array_sum($quantities) < 1) {
                throw new InvalidArgumentException(__('Geef minstens één regel een aantal boven nul, of sluit de retour zonder creditering.'));
            }

            if ($refundDiscount) {
                $this->guardFullReturn($order, $perOrderProduct);
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
                // Een cadeaubon wordt niet automatisch teruggestort:
                // refillGiftcardFromPaidOrder() telt de hele orderkorting bij het
                // saldo op, zonder te kijken hoeveel er terugkomt en zonder te
                // onthouden dat het al gebeurd is. Bij een deelretour of een
                // tweede retour zou de klant het volle bedrag opnieuw krijgen.
                // De beheerder stort met de hand terug als dat aan de orde is.
                refillGiftcard: false,
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

        OrderReturnProcessedEvent::dispatch($return, $creditOrder);

        $this->mailCustomer($return);

        return $creditOrder;
    }

    /**
     * De creditorders die al aan deze bestelling hangen, ongeacht waar ze
     * vandaan kwamen (eerdere retour, annuleerknop, orderwijziging).
     *
     * @return \Illuminate\Support\Collection<int, Order>
     */
    public static function existingCreditOrders(Order $order): \Illuminate\Support\Collection
    {
        return $order->creditOrders()->orderBy('id')->get();
    }

    /**
     * "Korting verrekenen" trekt de hele vaste korting van de bestelling van het
     * creditbedrag af, ongeacht welk deel er terugkomt, en zou dat bij elke
     * volgende retour opnieuw doen. Verrekenen mag daarom alleen als er na deze
     * verwerking niets meer te retourneren valt.
     *
     * @param  array<int, int>  $perOrderProduct  te verwerken aantal per orderproduct
     */
    protected function guardFullReturn(Order $order, array $perOrderProduct): void
    {
        // De relatie kan door een eerdere lading verouderde returned_quantity's
        // bevatten; het restant moet uit de database komen.
        $order->unsetRelation('orderProducts');

        foreach (ReturnableLines::forOrder($order) as $orderProduct) {
            $rest = ReturnableLines::remaining($orderProduct) - (int) ($perOrderProduct[$orderProduct->id] ?? 0);
            if ($rest > 0) {
                throw new InvalidArgumentException(__('Korting verrekenen kan alleen bij een volledige retour: er blijven nog producten over die niet geretourneerd zijn.'));
            }
        }
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
