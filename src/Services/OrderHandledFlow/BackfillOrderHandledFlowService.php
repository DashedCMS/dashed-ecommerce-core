<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\OrderHandledFlow;

use Illuminate\Support\Carbon;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;
use Dashed\DashedEcommerceCore\Jobs\OrderHandledFlow\SendOrderHandledEmailJob;

/**
 * Backfill: voor een actieve order-opvolg flow plant alsnog de stappen voor
 * bestaande orders die binnen het opgegeven aantal dagen op de geconfigureerde
 * fulfillment-status (trigger_status) van de flow zijn gezet maar nog niet in
 * de flow zitten. Records met een bestaande enrollment (gecanceld of niet)
 * worden overgeslagen zodat we niet dubbel inschrijven.
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

        $flow = $flow ?? OrderHandledFlow::getActiveForStatus('handled');
        if (! $flow || ! $flow->is_active) {
            return $stats;
        }

        $steps = $flow->activeSteps()->get();
        if ($steps->isEmpty()) {
            return $stats;
        }

        $triggerStatus = (string) ($flow->trigger_status ?: 'handled');
        $since = Carbon::now()->subDays(max(1, $sinceDays))->startOfDay();

        $orders = Order::query()
            ->where('fulfillment_status', $triggerStatus)
            ->where('updated_at', '>=', $since)
            ->get();

        foreach ($orders as $order) {
            if (blank($order->email)) {
                $stats['orders_skipped_no_email']++;

                continue;
            }

            $existingEnrollment = OrderFlowEnrollment::query()
                ->where('order_id', $order->id)
                ->where('flow_id', $flow->id)
                ->first();

            if ($existingEnrollment) {
                if ($existingEnrollment->cancelled_at !== null) {
                    $stats['orders_skipped_cancelled']++;
                } else {
                    $stats['orders_skipped_already_started']++;
                }

                continue;
            }

            try {
                OrderFlowEnrollment::create([
                    'order_id' => $order->id,
                    'flow_id' => $flow->id,
                    'started_at' => now(),
                    'cancelled_at' => null,
                    'cancelled_reason' => null,
                ]);
            } catch (\Throwable $e) {
                // Race-condition op de unique-index, gewoon overslaan.
                $stats['orders_skipped_already_started']++;

                continue;
            }

            // Backwards-compat: hou de legacy-kolom in sync voor "handled"-flows.
            if ($triggerStatus === 'handled' && $order->handled_flow_started_at === null) {
                $order->forceFill(['handled_flow_started_at' => now()])->save();
            }

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
