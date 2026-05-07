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
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'sent_steps' => 'array',
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
