<?php

namespace Dashed\DashedEcommerceCore\Listeners\OrderHandledFlow;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

class QueueOrderFlowEmailsListener implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function handle(OrderFulfillmentStatusChangedEvent $event): void
    {
        $order = $event->order;
        $newStatus = $event->newStatus;

        if (blank($order->email)) {
            return;
        }

        // Bol.com-orders horen nooit in marketing-/nieuwsbrief-flows: die
        // e-mailadressen zijn van Bol-shoppers, niet van onze shop.
        if ((string) $order->order_origin === 'Bol') {
            return;
        }

        // Concept-/proforma-bestellingen mogen géén opvolg-mails triggeren.
        // De fulfillment-status kan ook tijdens concept-bewerken muteren,
        // maar pas na daadwerkelijke betaling (invoice_id != PROFORMA en
        // niet in concept/cancelled-status) is de flow van toepassing.
        if (in_array((string) $order->invoice_id, ['PROFORMA', 'RETURN'], true)) {
            return;
        }
        if (in_array((string) $order->status, ['concept', 'cancelled'], true)) {
            return;
        }
        // Alleen betaalde bestellingen mogen in de opvolg-flow. Een order kan
        // op 'handled' gezet worden zonder betaling; die hoort er niet in.
        if (! $order->isPaidFor()) {
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

            // Per-flow filter op order_origin. Leeg = alle origins. Orders zonder
            // expliciete origin worden behandeld als 'own' (eigen shop).
            $allowedOrigins = $flow->order_origins ?: [];
            if (! empty($allowedOrigins) && ! in_array($order->order_origin ?? 'own', $allowedOrigins, true)) {
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
                $enrollment = OrderFlowEnrollment::create([
                    'order_id' => $order->id,
                    'flow_id' => $flow->id,
                    'started_at' => now(),
                    'cancelled_at' => null,
                    'cancelled_reason' => null,
                    'chosen_review_url_label' => $picked['label'] ?? null,
                    'chosen_review_url' => $picked['url'] ?? null,
                ]);

                // Eerstvolgende mail-tijdstip alvast vastleggen zodat de
                // Filament-tabel hierop kan sorteren / filteren.
                $enrollment->recomputeNextMailAt();
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

            // Verzending zelf wordt afgehandeld door de scheduled command
            // dashed:send-order-handled-flow-emails (uurlijks). De enrollment
            // heeft via recomputeNextMailAt() al een next_mail_at; de command
            // pakt 'm op zodra die tijd verstreken is.
        }
    }
}
