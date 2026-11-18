<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bijbestelling: order_id is een betaalde order die in dezelfde doos meegaat met
 * parent_order_id. Status LINKED = meegestuurd (geen eigen verzendlabel),
 * SEPARATE = toch los verzonden (reden in reason).
 */
class OrderAddOn extends Model
{
    public const LINKED = 'linked';

    public const SEPARATE = 'separate';

    protected $table = 'dashed__order_add_ons';

    protected $fillable = [
        'order_id',
        'parent_order_id',
        'status',
        'reason',
        'paid_at',
        'handled_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'handled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    public function isLinked(): bool
    {
        return $this->status === self::LINKED;
    }

    public function isSeparate(): bool
    {
        return $this->status === self::SEPARATE;
    }
}
