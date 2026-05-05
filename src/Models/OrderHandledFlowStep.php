<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderHandledFlowStep extends Model
{
    use HasTranslations;

    protected $table = 'dashed__order_handled_flow_steps';

    protected $fillable = [
        'flow_id',
        'sort_order',
        'send_after_minutes',
        'is_active',
        'subject',
        'blocks',
    ];

    public array $translatable = [
        'subject',
        'blocks',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'send_after_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(OrderHandledFlow::class, 'flow_id');
    }
}
