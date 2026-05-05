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
            Log::info('order-handled-flow: order of stap niet meer aanwezig - skip', [
                'order_id' => $this->orderId,
                'flow_step_id' => $this->flowStepId,
            ]);

            return;
        }

        // 1. Order moet nog steeds afgehandeld zijn.
        if ($order->fulfillment_status !== 'handled') {
            Log::info('order-handled-flow: fulfillment_status niet langer handled - skip', [
                'order_id' => $order->id,
                'fulfillment_status' => $order->fulfillment_status,
            ]);

            return;
        }

        // 2. Flow geannuleerd via klik of unsubscribe.
        if ($order->handled_flow_cancelled_at !== null) {
            Log::info('order-handled-flow: flow geannuleerd voor deze order - skip', [
                'order_id' => $order->id,
                'cancelled_at' => $order->handled_flow_cancelled_at,
            ]);

            return;
        }

        // 4. Flow of stap mag niet langer inactief zijn.
        $flow = $step->flow;
        if (! $flow || ! $flow->is_active || ! $step->is_active) {
            Log::info('order-handled-flow: flow of stap niet meer actief - skip', [
                'order_id' => $order->id,
                'flow_step_id' => $step->id,
                'flow_active' => $flow?->is_active,
                'step_active' => $step->is_active,
            ]);

            return;
        }

        // 3. Klant heeft recent een nieuwe betaalde bestelling geplaatst.
        $cooldownDays = (int) ($flow->skip_if_recently_ordered_within_days ?? 0);
        if ($cooldownDays > 0 && ! blank($order->email)) {
            $hasRecentPaid = Order::query()
                ->where('email', $order->email)
                ->where('id', '!=', $order->id)
                ->isPaid()
                ->where('created_at', '>=', now()->subDays($cooldownDays))
                ->exists();

            if ($hasRecentPaid) {
                Log::info('order-handled-flow: recente betaalde bestelling gevonden - cancel + skip', [
                    'order_id' => $order->id,
                    'cooldown_days' => $cooldownDays,
                ]);

                $order->forceFill(['handled_flow_cancelled_at' => now()])->save();

                return;
            }
        }

        try {
            $locale = $order->locale ?: app()->getLocale();
            Mail::to($order->email)->send(new OrderHandledMail($order, $step, $locale));
        } catch (\Throwable $e) {
            report($e);
            Log::warning('order-handled-flow: mail kon niet verstuurd worden', [
                'order_id' => $order->id,
                'flow_step_id' => $step->id,
                'error' => $e->getMessage(),
            ]);

            // Postmark levert bv. een 406 als het adres als inactive
            // is gemarkeerd (hard bounce / spam complaint). Verdere
            // stappen voor dezelfde ontvanger zouden ook falen, dus
            // we cancellen de flow en laten de job slagen zodat hij
            // niet eindeloos retried wordt.
            $order->forceFill(['handled_flow_cancelled_at' => now()])->save();
        }
    }
}
