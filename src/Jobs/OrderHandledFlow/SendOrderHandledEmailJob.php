<?php

namespace Dashed\DashedEcommerceCore\Jobs\OrderHandledFlow;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Mail\OrderHandledMail;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlowStep;

class SendOrderHandledEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $orderId,
        public int $flowStepId,
    ) {
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        $step = OrderHandledFlowStep::with('flow')->find($this->flowStepId);

        if (! $order || ! $step) {
            Log::info('order-flow: order of stap niet meer aanwezig - skip', [
                'order_id' => $this->orderId,
                'flow_step_id' => $this->flowStepId,
            ]);

            return;
        }

        $flow = $step->flow;
        if (! $flow) {
            Log::info('order-flow: flow niet meer aanwezig - skip', [
                'order_id' => $order->id,
                'flow_step_id' => $step->id,
            ]);

            return;
        }

        $triggerStatus = (string) ($flow->trigger_status ?: 'handled');

        // 1. Order moet nog steeds in de status staan waarop de flow getriggerd is.
        if ($order->fulfillment_status !== $triggerStatus) {
            Log::info('order-flow: fulfillment_status niet langer gelijk aan trigger - skip', [
                'order_id' => $order->id,
                'fulfillment_status' => $order->fulfillment_status,
                'trigger_status' => $triggerStatus,
            ]);

            return;
        }

        // 2. Inschrijving moet bestaan en niet geannuleerd zijn (klik / unsubscribe / cooldown).
        $enrollment = OrderFlowEnrollment::query()
            ->where('order_id', $order->id)
            ->where('flow_id', $flow->id)
            ->first();

        if (! $enrollment) {
            Log::info('order-flow: geen enrollment voor (order, flow) - skip', [
                'order_id' => $order->id,
                'flow_id' => $flow->id,
            ]);

            return;
        }

        if ($enrollment->cancelled_at !== null) {
            Log::info('order-flow: enrollment geannuleerd - skip', [
                'order_id' => $order->id,
                'flow_id' => $flow->id,
                'cancelled_at' => $enrollment->cancelled_at,
                'cancelled_reason' => $enrollment->cancelled_reason,
            ]);

            return;
        }

        // Backwards-compat fallback: legacy "handled"-flows zonder enrollment-rij
        // (oude migratie zonder backfill) keken naar deze kolom op de order.
        if ($triggerStatus === 'handled' && $order->handled_flow_cancelled_at !== null) {
            Log::info('order-flow: legacy handled_flow_cancelled_at gezet - skip', [
                'order_id' => $order->id,
                'cancelled_at' => $order->handled_flow_cancelled_at,
            ]);

            return;
        }

        // 3. Flow of stap mag niet langer inactief zijn.
        if (! $flow->is_active || ! $step->is_active) {
            Log::info('order-flow: flow of stap niet meer actief - skip', [
                'order_id' => $order->id,
                'flow_step_id' => $step->id,
                'flow_active' => $flow->is_active,
                'step_active' => $step->is_active,
            ]);

            return;
        }

        // 4. Cooldown: klant heeft recent een nieuwe betaalde bestelling geplaatst.
        $cooldownDays = (int) ($flow->skip_if_recently_ordered_within_days ?? 0);
        if ($cooldownDays > 0 && ! blank($order->email)) {
            $hasRecentPaid = Order::query()
                ->where('email', $order->email)
                ->where('id', '!=', $order->id)
                ->isPaid()
                ->where('created_at', '>=', now()->subDays($cooldownDays))
                ->exists();

            if ($hasRecentPaid) {
                Log::info('order-flow: recente betaalde bestelling gevonden - cancel + skip', [
                    'order_id' => $order->id,
                    'flow_id' => $flow->id,
                    'cooldown_days' => $cooldownDays,
                ]);

                $enrollment->forceFill([
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'recent_paid_order',
                ])->save();

                if ($triggerStatus === 'handled') {
                    $order->forceFill(['handled_flow_cancelled_at' => now()])->save();
                }

                return;
            }
        }

        try {
            $locale = $order->locale ?: app()->getLocale();
            Mail::to($order->email)->send(new OrderHandledMail($order, $step, $locale));
        } catch (\Throwable $e) {
            report($e);
            Log::warning('order-flow: mail kon niet verstuurd worden', [
                'order_id' => $order->id,
                'flow_id' => $flow->id,
                'flow_step_id' => $step->id,
                'error' => $e->getMessage(),
            ]);

            // Postmark levert bv. een 406 als het adres als inactive is
            // gemarkeerd (hard bounce / spam complaint). Verdere stappen voor
            // dezelfde ontvanger zouden ook falen, dus we cancellen de
            // inschrijving en laten de job slagen zodat hij niet eindeloos
            // retried wordt.
            $enrollment->forceFill([
                'cancelled_at' => now(),
                'cancelled_reason' => 'mail_failed',
            ])->save();

            if ($triggerStatus === 'handled') {
                $order->forceFill(['handled_flow_cancelled_at' => now()])->save();
            }
        }
    }
}
