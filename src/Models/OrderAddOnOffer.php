<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Het bijbestel-aanbod bij een order: wanneer getoond, gemaild (of overgeslagen) en geklikt. */
class OrderAddOnOffer extends Model
{
    protected $table = 'dashed__order_add_on_offers';

    protected $fillable = [
        'order_id',
        'email',
        'shown_at',
        'mail_sent_at',
        'mail_skipped',
        'clicked_at',
        'clicks',
    ];

    protected $casts = [
        'shown_at' => 'datetime',
        'mail_sent_at' => 'datetime',
        'clicked_at' => 'datetime',
        'clicks' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
