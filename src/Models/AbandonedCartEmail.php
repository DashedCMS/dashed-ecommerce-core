<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCartEmail extends Model
{
    protected $table = 'dashed__abandoned_cart_emails';

    protected $fillable = [
        'cart_id',
        'email',
        'email_number',
        'sent_at',
        'cancelled_at',
        'discount_code_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
    }

    public static function cancelAllForCart(int $cartId): void
    {
        static::where('cart_id', $cartId)
            ->whereNull('cancelled_at')
            ->whereNull('sent_at')
            ->update(['cancelled_at' => now()]);
    }

    public function isPending(): bool
    {
        return $this->sent_at === null && $this->cancelled_at === null;
    }
}
