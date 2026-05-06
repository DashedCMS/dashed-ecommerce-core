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
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(OrderHandledFlow::class, 'flow_id');
    }
}
