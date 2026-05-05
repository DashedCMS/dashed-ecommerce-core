<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderHandledClick extends Model
{
    protected $table = 'dashed__order_handled_clicks';

    protected $fillable = [
        'order_id',
        'flow_step_id',
        'link_type',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function flowStep(): BelongsTo
    {
        return $this->belongsTo(OrderHandledFlowStep::class, 'flow_step_id');
    }
}
