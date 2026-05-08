<?php

namespace Dashed\DashedEcommerceCore\Listeners\OrderHandledFlow;

use Illuminate\Support\Facades\Log;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;
use Dashed\DashedEcommerceCore\Jobs\OrderHandledFlow\SendOrderHandledEmailJob;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

class QueueOrderFlowEmailsListener
{
    public function handle(OrderFulfillmentStatusChangedEvent $event): void
    {
        $order = $event->order;
        $newStatus = $event->newStatus;

        if (blank($order->email)) {
            return;
        }

        $flows = OrderHandledFlow::query()
            ->where('is_active', true)
            ->where('trigger_status', $newStatus)
            ->get();

        if ($flows->isEmpty()) {
            return;
        }

        foreach ($flows as $flow) {
            $steps = $flow->activeSteps()->get();
            if ($steps->isEmpty()) {
                continue;
            }

            // Per (order, flow) maximaal 1 enrollment ooit. Als hij al bestaat
            // (ook als hij gecanceld is), slaan we deze flow over voor deze order
            // zodat we niet dubbel inschrijven.
            $alreadyEnrolled = OrderFlowEnrollment::query()
                ->where('order_id', $order->id)
                ->where('flow_id', $flow->id)
                ->exists();

            if ($alreadyEnrolled) {
                Log::info('order-flow: order al ingeschreven op flow - skip', [
                    'order_id' => $order->id,
                    'flow_id' => $flow->id,
                    'trigger_status' => $newStatus,
                ]);

                continue;
            }

            // Per inschrijving 1 review-URL kiezen (gewogen willekeurig). Zo
            // ziet de klant in elke vervolg-mail dezelfde review-link en kunnen
            // we conversie per platform meten. Null = geen URLs ingesteld en
            // ook geen fallback in Customsetting; mailer regelt dat verder.
            $picked = $flow->pickReviewUrl();

            try {
                OrderFlowEnrollment::create([
                    'order_id' => $order->id,
                    'flow_id' => $flow->id,
                    'started_at' => now(),
                    'cancelled_at' => null,
                    'cancelled_reason' => null,
                    'chosen_review_url_label' => $picked['label'] ?? null,
                    'chosen_review_url' => $picked['url'] ?? null,
                ]);
            } catch (\Throwable $e) {
                // Race-condition: unique-constraint kan klappen als 2 status-wisselingen
                // tegelijk verwerkt worden. Niet fataal, gewoon doorgaan en de jobs
                // niet (nogmaals) inschieten.
                Log::warning('order-flow: enrollment kon niet aangemaakt worden', [
                    'order_id' => $order->id,
                    'flow_id' => $flow->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            // Backwards-compat: blijf de legacy-kolommen op de order vullen
            // wanneer een "handled"-flow start, zodat oudere reads buiten de
            // enrollment-tabel niet stuk gaan.
            if ($newStatus === 'handled' && $order->handled_flow_started_at === null) {
                $order->forceFill([
                    'handled_flow_started_at' => now(),
                    'handled_flow_cancelled_at' => null,
                ])->save();
            }

            foreach ($steps as $step) {
                SendOrderHandledEmailJob::dispatch($order->id, $step->id)
                    ->delay(now()->addMinutes((int) $step->send_after_minutes));
            }
        }
    }
}
