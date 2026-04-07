<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCartClick extends Model
{
    protected $table = 'dashed__abandoned_cart_clicks';

    protected $fillable = [
        'abandoned_cart_email_id',
        'link_type',
    ];

    public function abandonedCartEmail(): BelongsTo
    {
        return $this->belongsTo(AbandonedCartEmail::class, 'abandoned_cart_email_id');
    }
}
