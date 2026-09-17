<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Een beheerder die een bestelling annuleert terwijl er nog een retour open
 * staat, heeft die retour daarmee feitelijk verwerkt: de creditorder van de
 * annulering is de creditering die de retour zou opleveren. Deze klasse zet
 * de open retouren (aangevraagd of goedgekeurd) op Verwerkt en koppelt ze aan
 * die creditorder, zodat de retourlijst laat zien dat de retour geld heeft
 * gekost en een tweede retour niet meer kan dan het restant.
 *
 * Bewust zonder klantmail en zonder OrderReturnProcessedEvent: de annulering
 * heeft de klant al bericht, en het event zou bij een Bol-retour een tweede
 * betaling "Via Bol" op de creditorder boeken naast die van de annulering.
 */
class CancellationReturnSettler
{
    /**
     * @param  array<int, int>  $cancelledQuantities  geannuleerd aantal per order_product_id
     * @return int aantal afgehandelde retouren
     */
    public function settle(Order $order, Order $creditOrder, array $cancelledQuantities): int
    {
        return DB::transaction(function () use ($order, $creditOrder, $cancelledQuantities) {
            $returns = OrderReturn::query()
                ->where('order_id', $order->id)
                ->open()
                ->lockForUpdate()
                ->with('lines.orderProduct')
                ->get();

            foreach ($returns as $return) {
                $this->settleReturn($return, $creditOrder, $cancelledQuantities);
            }

            if ($returns->isNotEmpty()) {
                $order->retour_status = 'handled';
                $order->save();
            }

            return $returns->count();
        });
    }

    /**
     * @param  array<int, int>  $cancelledQuantities
     */
    protected function settleReturn(OrderReturn $return, Order $creditOrder, array $cancelledQuantities): void
    {
        foreach ($return->lines as $line) {
            $orderProductId = (int) $line->order_product_id;
            $cancelled = (int) ($cancelledQuantities[$orderProductId] ?? 0);
            $remaining = $line->orderProduct ? ReturnableLines::remaining($line->orderProduct) : 0;
            $quantity = max(0, min((int) $line->quantity, $cancelled, $remaining));

            $line->update(['processed_quantity' => $quantity]);
            if ($quantity > 0) {
                OrderProduct::withoutEvents(fn () => OrderProduct::query()
                    ->whereKey($orderProductId)
                    ->increment('returned_quantity', $quantity));
            }
        }

        $return->credit_order_id = $creditOrder->id;
        $return->status = OrderReturn::STATUS_HANDLED;
        $return->processed_at = now();
        $return->handled_at = now();
        $return->save();

        OrderLog::createLog(
            orderId: $return->order_id,
            tag: 'order.return-handled-by-cancellation',
            note: __('Creditorder :nummer', ['nummer' => $creditOrder->invoice_id]),
        );
    }
}
