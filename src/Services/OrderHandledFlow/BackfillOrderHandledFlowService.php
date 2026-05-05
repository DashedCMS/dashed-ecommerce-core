<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\OrderHandledFlow;

use Illuminate\Support\Carbon;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Jobs\OrderHandledFlow\SendOrderHandledEmailJob;

/**
 * Backfill: voor een actieve order-handled flow plant alsnog de stappen voor
 * bestaande orders die binnen het opgegeven aantal dagen op fulfillment_status
 * = handled zijn gezet maar nog niet in de flow zitten. Records met
 * handled_flow_started_at of handled_flow_cancelled_at worden overgeslagen.
 */
class BackfillOrderHandledFlowService
{
    /**
     * @return array{
     *     orders_started: int,
     *     orders_skipped_already_started: int,
     *     orders_skipped_cancelled: int,
     *     orders_skipped_no_email: int,
     *     emails_dispatched: int,
     * }
     */
    public function run(?OrderHandledFlow $flow = null, int $sinceDays = 30): array
    {
        $stats = [
            'orders_started' => 0,
            'orders_skipped_already_started' => 0,
            'orders_skipped_cancelled' => 0,
            'orders_skipped_no_email' => 0,
            'emails_dispatched' => 0,
        ];

        $flow = $flow ?? OrderHandledFlow::getActive();
        if (! $flow || ! $flow->is_active) {
            return $stats;
        }

        $steps = $flow->activeSteps()->get();
        if ($steps->isEmpty()) {
            return $stats;
        }

        $since = Carbon::now()->subDays(max(1, $sinceDays))->startOfDay();

        $orders = Order::query()
            ->where('fulfillment_status', 'handled')
            ->where('updated_at', '>=', $since)
            ->whereNull('handled_flow_started_at')
            ->get();

        foreach ($orders as $order) {
            if (blank($order->email)) {
                $stats['orders_skipped_no_email']++;

                continue;
            }

            if ($order->handled_flow_cancelled_at !== null) {
                $stats['orders_skipped_cancelled']++;

                continue;
            }

            if ($order->handled_flow_started_at !== null) {
                $stats['orders_skipped_already_started']++;

                continue;
            }

            $order->forceFill(['handled_flow_started_at' => now()])->save();

            foreach ($steps as $step) {
                SendOrderHandledEmailJob::dispatch($order->id, $step->id)
                    ->delay(now()->addMinutes((int) $step->send_after_minutes));
                $stats['emails_dispatched']++;
            }

            $stats['orders_started']++;
        }

        return $stats;
    }
}
