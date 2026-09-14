<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model
{
    protected $table = 'dashed__wishlist_items';

    protected $fillable = ['wishlist_id', 'product_id', 'price_at_add'];

    protected $casts = ['price_at_add' => 'float'];

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class, 'wishlist_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** Of de huidige prijs onder de prijs op het moment van toevoegen ligt. */
    public function priceDropped(): bool
    {
        $huidig = $this->product?->currentPrice;

        return $this->price_at_add !== null && $huidig !== null && (float) $huidig < (float) $this->price_at_add - 0.005;
    }
}
