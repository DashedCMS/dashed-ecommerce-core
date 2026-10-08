<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Klantsignalen van het moment van bestellen (cookies, user agent, consent).
 * Bij de betaal-webhook zijn die er niet meer. Bestaat alleen voor orders uit
 * de webshop-checkout.
 */
class OrderTracking extends Model
{
    protected $table = 'dashed__order_tracking';

    protected $guarded = [];

    protected $casts = [
        'marketing_consent' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
