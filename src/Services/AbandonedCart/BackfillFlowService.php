<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\AbandonedCart;

use Illuminate\Support\Carbon;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;

/**
 * Backfill: voor een (gewijzigde of nieuwe) flow plant de emails alsnog in
 * voor bestaande, eligible carts en/of cancelled orders.
 */
class BackfillFlowService
{
    /**
     * @return array{
     *     carts_scheduled: int,
     *     carts_skipped_existing: int,
     *     orders_scheduled: int,
     *     orders_skipped_existing: int,
     * }
     */
    public function run(AbandonedCartFlow $flow, int $sinceDays = 30): array
    {
        $stats = [
            'carts_scheduled' => 0,
            'carts_skipped_existing' => 0,
            'orders_scheduled' => 0,
            'orders_skipped_existing' => 0,
        ];

        $steps = $flow->steps()->where('enabled', true)->orderBy('sort_order')->get();

        if ($steps->isEmpty() || ! $flow->is_active) {
            return $stats;
        }

        $stepIds = $steps->pluck('id')->all();
        $since = Carbon::now()->subDays(max(1, $sinceDays))->startOfDay();

        if ($flow->hasTrigger('cart_with_email')) {
            $carts = Cart::query()
                ->whereNotNull('abandoned_email')
                ->where('abandoned_email', '!=', '')
                ->whereHas('items')
                ->where('created_at', '>=', $since)
                ->get();

            foreach ($carts as $cart) {
                if ($this->cartAlreadyScheduledForFlow($cart->id, $stepIds)) {
                    $stats['carts_skipped_existing']++;

                    continue;
                }

                $this->scheduleStepsForCart($steps, $cart);
                $stats['carts_scheduled']++;
            }
        }

        if ($flow->hasTrigger('cancelled_order')) {
            $orders = Order::query()
                ->where('status', 'cancelled')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->where('created_at', '>=', $since)
                ->whereDoesntHave('orderPayments', fn ($q) => $q->where('status', 'paid'))
                ->get();

            foreach ($orders as $order) {
                if ($this->orderAlreadyScheduledForFlow($order->id, $stepIds)) {
                    $stats['orders_skipped_existing']++;

                    continue;
                }

                $this->scheduleStepsForOrder($steps, $order);
                $stats['orders_scheduled']++;
            }
        }

        return $stats;
    }

    /**
     * @param  array<int>  $stepIds
     */
    private function cartAlreadyScheduledForFlow(int $cartId, array $stepIds): bool
    {
        return AbandonedCartEmail::query()
            ->where('cart_id', $cartId)
            ->whereIn('flow_step_id', $stepIds)
            ->exists();
    }

    /**
     * @param  array<int>  $stepIds
     */
    private function orderAlreadyScheduledForFlow(int $orderId, array $stepIds): bool
    {
        return AbandonedCartEmail::query()
            ->where('cancelled_order_id', $orderId)
            ->whereIn('flow_step_id', $stepIds)
            ->exists();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, AbandonedCartFlowStep>  $steps
     */
    private function scheduleStepsForCart($steps, Cart $cart): void
    {
        $cumulativeHours = 0;
        $now = now();

        foreach ($steps as $step) {
            $cumulativeHours += $this->delayInHours($step);

            AbandonedCartEmail::create([
                'cart_id' => $cart->id,
                'trigger_type' => 'cart_with_email',
                'email' => $cart->abandoned_email,
                'email_number' => $step->sort_order,
                'flow_step_id' => $step->id,
                'send_at' => $now->copy()->addHours($cumulativeHours),
            ]);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, AbandonedCartFlowStep>  $steps
     */
    private function scheduleStepsForOrder($steps, Order $order): void
    {
        $cumulativeHours = 0;
        $now = now();

        foreach ($steps as $step) {
            $cumulativeHours += $this->delayInHours($step);

            AbandonedCartEmail::create([
                'trigger_type' => 'cancelled_order',
                'cancelled_order_id' => $order->id,
                'cart_id' => null,
                'email' => $order->email,
                'email_number' => $step->sort_order,
                'flow_step_id' => $step->id,
                'send_at' => $now->copy()->addHours($cumulativeHours),
            ]);
        }
    }

    private function delayInHours(AbandonedCartFlowStep $step): int
    {
        if (isset($step->delay_unit) && $step->delay_unit === 'days') {
            return (int) ($step->delay_value ?? 0) * 24;
        }

        if (isset($step->delay_value)) {
            return (int) $step->delay_value;
        }

        return (int) ($step->delay_in_hours ?? 0);
    }
}
