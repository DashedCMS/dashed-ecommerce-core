<?php

namespace Dashed\DashedEcommerceCore\Listeners\AbandonedCart;

use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlowStep;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;

class QueueAbandonedCartEmailsForOrderListener
{
    public function handle(OrderCancelledEvent $event): void
    {
        $order = $event->order;

        if (blank($order->email)) {
            return;
        }

        if ($order->orderPayments()->where('status', 'paid')->exists()) {
            return;
        }

        $now = now();

        AbandonedCartFlow::where('is_active', true)->get()->each(function (AbandonedCartFlow $flow) use ($order, $now) {
            if (! $flow->hasTrigger('cancelled_order')) {
                return;
            }

            $cumulativeHours = 0;

            $flow->steps()
                ->where('enabled', true)
                ->orderBy('sort_order')
                ->get()
                ->each(function (AbandonedCartFlowStep $step) use ($order, $now, &$cumulativeHours) {
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
                });
        });
    }

    private function delayInHours(AbandonedCartFlowStep $step): int
    {
        return $step->delay_unit === 'days'
            ? (int) $step->delay_value * 24
            : (int) $step->delay_value;
    }
}
