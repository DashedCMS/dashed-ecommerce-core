<?php

namespace Dashed\DashedEcommerceCore\Listeners\OrderHandledFlow;

use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsHandledEvent;
use Dashed\DashedEcommerceCore\Jobs\OrderHandledFlow\SendOrderHandledEmailJob;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;

class QueueOrderHandledEmailsListener
{
    public function handle(OrderMarkedAsHandledEvent $event): void
    {
        $order = $event->order;

        if ($order->fulfillment_status !== 'handled') {
            return;
        }

        if ($order->handled_flow_started_at !== null) {
            return;
        }

        if (blank($order->email)) {
            return;
        }

        $flow = OrderHandledFlow::getActive();
        if (! $flow) {
            return;
        }

        $steps = $flow->activeSteps()->get();
        if ($steps->isEmpty()) {
            return;
        }

        $order->forceFill([
            'handled_flow_started_at' => now(),
            'handled_flow_cancelled_at' => null,
        ])->save();

        foreach ($steps as $step) {
            SendOrderHandledEmailJob::dispatch($order->id, $step->id)
                ->delay(now()->addMinutes((int) $step->send_after_minutes));
        }
    }
}
