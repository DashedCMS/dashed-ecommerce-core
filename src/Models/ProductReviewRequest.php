<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Het reviewverzoek van één bestelling: ingepland zodra de bestelling op
 * Afgehandeld gaat, verstuurd door dashed:send-product-review-requests als
 * `due_at` voorbij is. Eén rij per bestelling (unieke index), dus een
 * bestelling die heen en weer wisselt krijgt nooit twee verzoeken.
 */
class ProductReviewRequest extends Model
{
    protected $table = 'dashed__product_review_requests';

    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
