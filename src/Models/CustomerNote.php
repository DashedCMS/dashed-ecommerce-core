<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interne notitie bij een klant (account of e-mailadres van een gast).
 * Alleen zichtbaar in het CMS.
 */
class CustomerNote extends Model
{
    protected $table = 'dashed__customer_notes';

    protected $guarded = [];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
