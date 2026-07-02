<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Mail\OrderHandledMail;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

/**
 * Poll-based dispatcher voor de order-handled-flow opvolg-mails. Vervangt
 * de eerder gebruikte delayed-queue-jobs (SendOrderHandledEmailJob) zodat
 * verloren queue jobs (deploy, redis flush, queue clear) niet leiden tot
 * eeuwig vastzittende enrollments. Inschrijvingen blijven in DB staan tot
 * elke stap verzonden is.
 */
class SendOrderHandledFlowEmails extends Command
{
    protected $signature = 'dashed:send-order-handled-flow-emails';

    protected $description = 'Verstuur openstaande order-handled-flow opvolg-mails die nu aan de beurt zijn.';

    public function handle(): int
    {
        $enrollments = OrderFlowEnrollment::query()
            ->whereNull('cancelled_at')
            ->where(function ($query) {
                // Snelle pad: inschrijvingen die aan de beurt zijn.
                $query->where('next_mail_at', '<=', now())
                    // Vangnet: inschrijvingen zonder next_mail_at zijn nooit
                    // ingepland (bv. legacy-inschrijvingen van vóór de
                    // poll-based verzender, of een bulk-import/backfill). Zonder
                    // dit filter blijven die permanent onzichtbaar en versturen
                    // ze nooit een mail. processEnrollment() herberekent
                    // next_mail_at en verstuurt indien inmiddels verschuldigd,
                    // of laat 'm met rust als er geen openstaande stap meer is.
                    ->orWhereNull('next_mail_at');
            })
            ->with(['order', 'flow.steps'])
            ->get();

        foreach ($enrollments as $enrollment) {
            try {
                $this->processEnrollment($enrollment);
            } catch (Throwable $e) {
                report($e);
                Log::warning('order-flow: enrollment kon niet verwerkt worden', [
                    'enrollment_id' => $enrollment->id,
                    'order_id' => $enrollment->order_id,
                    'flow_id' => $enrollment->flow_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }

    protected function processEnrollment(OrderFlowEnrollment $enrollment): void
    {
        $order = $enrollment->order;
        $flow = $enrollment->flow;

        if (! $flow || ! $order) {
            $enrollment->forceFill([
                'cancelled_at' => now(),
                'cancelled_reason' => 'order_or_flow_missing',
                'next_mail_at' => null,
            ])->save();

            return;
        }

        if (! $flow->is_active) {
            // Flow tijdelijk uit; laat enrollment staan zodat hij opgepakt wordt
            // zodra de flow weer aanstaat. Geen update nodig.
            return;
        }

        if (in_array((string) $order->status, ['concept', 'cancelled'], true)) {
            $enrollment->forceFill([
                'cancelled_at' => now(),
                'cancelled_reason' => 'order_cancelled_or_concept',
                'next_mail_at' => null,
            ])->save();

            return;
        }

        // Alleen betaalde, echte bestellingen horen in de flow. Proforma-/
        // retour-facturen en niet-betaalde orders mogen nooit een opvolg-mail
        // krijgen; annuleer de inschrijving als de order niet (meer)
        // kwalificeert. Dit is de laatste vangst, ongeacht hoe de inschrijving
        // ooit is aangemaakt (listener, bulk-backfill of import).
        if (in_array((string) $order->invoice_id, ['PROFORMA', 'RETURN'], true) || ! $order->isPaidFor()) {
            $enrollment->forceFill([
                'cancelled_at' => now(),
                'cancelled_reason' => 'order_not_paid_or_proforma',
                'next_mail_at' => null,
            ])->save();

            if ((string) $flow->trigger_status === 'handled') {
                $order->forceFill(['handled_flow_cancelled_at' => now()])->save();
            }

            return;
        }

        if (blank($order->email)) {
            $enrollment->forceFill([
                'cancelled_at' => now(),
                'cancelled_reason' => 'no_email',
                'next_mail_at' => null,
            ])->save();

            return;
        }

        // Cooldown: klant heeft recent een nieuwe betaalde bestelling geplaatst.
        $cooldownDays = (int) ($flow->skip_if_recently_ordered_within_days ?? 0);
        if ($cooldownDays > 0) {
            $hasRecentPaid = Order::query()
                ->where('email', $order->email)
                ->where('id', '!=', $order->id)
                ->isPaid()
                ->where('created_at', '>=', now()->subDays($cooldownDays))
                ->exists();

            if ($hasRecentPaid) {
                $enrollment->forceFill([
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'recent_paid_order',
                    'next_mail_at' => null,
                ])->save();

                if ((string) $flow->trigger_status === 'handled') {
                    $order->forceFill(['handled_flow_cancelled_at' => now()])->save();
                }

                return;
            }
        }

        $startedAt = $enrollment->started_at ?: ($enrollment->created_at ?? now());
        $sent = is_array($enrollment->sent_steps) ? $enrollment->sent_steps : [];
        $sentIds = array_map('strval', array_keys($sent));

        $dueStep = $flow->steps()
            ->where('is_active', true)
            ->whereNotIn('id', $sentIds ?: [0])
            ->orderBy('send_after_minutes')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(function ($step) use ($startedAt) {
                $due = $startedAt->copy()->addMinutes((int) $step->send_after_minutes);

                return $due <= now();
            });

        if (! $dueStep) {
            // Geen due stap meer; herrekenen voor UI en done.
            $enrollment->recomputeNextMailAt();

            return;
        }

        try {
            $locale = $order->locale ?: app()->getLocale();
            Mail::to($order->email)->send(new OrderHandledMail($order, $dueStep, $locale));
            $enrollment->markStepSent((int) $dueStep->id);
        } catch (Throwable $e) {
            report($e);
            Log::warning('order-flow: mail kon niet verstuurd worden', [
                'order_id' => $order->id,
                'flow_id' => $flow->id,
                'flow_step_id' => $dueStep->id,
                'error' => $e->getMessage(),
            ]);

            // Postmark levert bv. een 406 als het adres als inactive is
            // gemarkeerd. Verdere stappen voor dezelfde ontvanger zouden
            // ook falen, dus we cancellen de inschrijving.
            $enrollment->forceFill([
                'cancelled_at' => now(),
                'cancelled_reason' => 'mail_failed',
                'next_mail_at' => null,
            ])->save();

            if ((string) $flow->trigger_status === 'handled') {
                $order->forceFill(['handled_flow_cancelled_at' => now()])->save();
            }
        }
    }
}
