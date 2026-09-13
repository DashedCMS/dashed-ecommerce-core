<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\ReturnReason;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnApprovedMail;

/**
 * Een beheerder meldt zelf een retour aan (klant belde of mailde, of het
 * pakket lag ineens op de mat). De retour begint direct als goedgekeurd: de
 * beslissing is al genomen. Met "Klant informeren" gaat de goedkeuringsmail
 * en maken MyParcel/Veloyd een label via OrderReturnApprovedEvent; zonder
 * gaat het event met notifyCustomer=false, dus geen mail en geen label.
 * Een Bol-klant wordt nooit geinformeerd: Bol praat met die klant.
 */
class ReturnRegistrar
{
    /**
     * @param  array<int, array{order_product_id: int, quantity: int, return_reason_id?: int|null, reason_note?: string|null}>  $lines
     * @param  array{admin_note?: string|null, notify_customer?: bool}  $options
     */
    public function register(Order $order, array $lines, array $options = []): OrderReturn
    {
        if (! in_array($order->status, OrderLookupService::ELIGIBLE_STATUSES, true)) {
            throw new InvalidArgumentException(__('Alleen een betaalde bestelling kan geretourneerd worden.'));
        }

        $open = OrderReturn::query()->where('order_id', $order->id)->open()->orderBy('id')->first();
        if ($open) {
            throw new InvalidArgumentException(__('Er staat al een open retour (#:id) voor deze bestelling. Verwerk of sluit die eerst.', ['id' => $open->id]));
        }

        $normalized = $this->normalize($order, $lines);

        $notify = (bool) ($options['notify_customer'] ?? true);
        $isBol = $order->order_origin === 'Bol';
        if ($isBol) {
            $notify = false;
        }

        $return = DB::transaction(function () use ($order, $normalized, $options) {
            $return = OrderReturn::create([
                'site_id' => $order->site_id,
                'order_id' => $order->id,
                'email' => $order->email,
                'status' => OrderReturn::STATUS_APPROVED,
                'requested_at' => now(),
                'approved_at' => now(),
                'auto_accepted' => false,
                'admin_note' => filled($options['admin_note'] ?? null) ? trim((string) $options['admin_note']) : null,
            ]);

            foreach ($normalized as $line) {
                $return->lines()->create($line);
            }

            $order->update(['retour_status' => 'waiting_for_return']);

            OrderLog::createLog(orderId: $order->id, tag: 'order.return-registered-by-admin');

            return $return;
        });

        $return->load(['order', 'lines.orderProduct', 'lines.returnReason']);

        if ($notify) {
            Mail::to($return->email)->queue(new OrderReturnApprovedMail($return));
        } elseif ($isBol) {
            OrderLog::createLog(orderId: $order->id, tag: 'order.return-mail-skipped-bol');
        }

        OrderReturnApprovedEvent::dispatch($return, $notify);

        return $return;
    }

    /**
     * Valideert alles voordat er iets geschreven wordt (alles of niets) en
     * voegt dubbele regels voor hetzelfde orderproduct samen.
     *
     * @return array<int, array{order_product_id: int, quantity: int, return_reason_id: int|null, reason_note: string|null}>
     */
    protected function normalize(Order $order, array $lines): array
    {
        if (empty($lines)) {
            throw new InvalidArgumentException(__('Geef minstens één regel op.'));
        }

        $orderProducts = $order->orderProducts()->get()->keyBy('id');
        $activeReasons = ReturnReason::active()->pluck('id')->all();
        $normalized = [];

        foreach ($lines as $line) {
            $orderProductId = (int) ($line['order_product_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);
            $orderProduct = $orderProducts->get($orderProductId);

            if (! $orderProduct) {
                throw new InvalidArgumentException(__('Regel :id hoort niet bij deze bestelling.', ['id' => $orderProductId]));
            }
            if (! ReturnableLines::isReturnable($orderProduct)) {
                throw new InvalidArgumentException(__(':naam kan niet geretourneerd worden.', ['naam' => $orderProduct->name]));
            }
            if ($quantity < 1) {
                throw new InvalidArgumentException(__('Het aantal voor :naam moet minimaal 1 zijn.', ['naam' => $orderProduct->name]));
            }

            $reasonId = (int) ($line['return_reason_id'] ?? 0);
            $normalized[$orderProductId] = [
                'order_product_id' => $orderProductId,
                'quantity' => ($normalized[$orderProductId]['quantity'] ?? 0) + $quantity,
                'return_reason_id' => in_array($reasonId, $activeReasons, true) ? $reasonId : null,
                'reason_note' => filled($line['reason_note'] ?? null) ? trim((string) $line['reason_note']) : null,
            ];

            $remaining = ReturnableLines::remaining($orderProduct);
            if ($normalized[$orderProductId]['quantity'] > $remaining) {
                throw new InvalidArgumentException(__('Voor :naam kan nog maar :aantal terug.', ['naam' => $orderProduct->name, 'aantal' => $remaining]));
            }
        }

        return array_values($normalized);
    }
}
