<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFlowEnrollment extends Model
{
    protected $table = 'dashed__order_flow_enrollments';

    protected $fillable = [
        'order_id',
        'flow_id',
        'started_at',
        'cancelled_at',
        'cancelled_reason',
        'chosen_review_url_label',
        'chosen_review_url',
        'sent_steps',
        'next_mail_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'sent_steps' => 'array',
        'next_mail_at' => 'datetime',
    ];

    /**
     * Markeer een stap als verstuurd voor deze inschrijving. Idempotent:
     * dubbele aanroepen overschrijven de eerste timestamp niet.
     */
    public function markStepSent(int $flowStepId): void
    {
        $sent = is_array($this->sent_steps) ? $this->sent_steps : [];
        $key = (string) $flowStepId;

        if (isset($sent[$key])) {
            return;
        }

        $sent[$key] = now()->toIso8601String();

        $this->forceFill(['sent_steps' => $sent])->save();

        // Direct herrekenen welke mail nu de volgende is voor deze
        // inschrijving, zodat de Filament-tabel kan sorteren op
        // verzendmoment zonder zelf de stappen te traversen.
        $this->recomputeNextMailAt();
    }

    /**
     * Bereken `next_mail_at` op basis van de eerstvolgende actieve stap die
     * nog niet in `sent_steps` staat. Stap-tijdstip = `started_at +
     * send_after_minutes`. Geannuleerde inschrijvingen krijgen NULL. Zonder
     * onverzonden stap idem.
     */
    public function recomputeNextMailAt(): void
    {
        $next = null;

        if (! $this->cancelled_at && $this->flow) {
            $sent = is_array($this->sent_steps) ? $this->sent_steps : [];
            $sentIds = array_map('strval', array_keys($sent));

            $startedAt = $this->started_at ?: $this->created_at ?? now();

            $nextStep = $this->flow
                ->steps()
                ->where('is_active', true)
                ->whereNotIn('id', $sentIds ?: [0])
                ->orderBy('send_after_minutes')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($nextStep) {
                $next = $startedAt->copy()->addMinutes((int) $nextStep->send_after_minutes);
            }
        }

        if (($this->next_mail_at?->toIso8601String()) !== $next?->toIso8601String()) {
            $this->forceFill(['next_mail_at' => $next])->save();
        }
    }

    public function sentStepCount(): int
    {
        return is_array($this->sent_steps) ? count($this->sent_steps) : 0;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(OrderHandledFlow::class, 'flow_id');
    }
}
