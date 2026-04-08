<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbandonedCartEmail extends Model
{
    protected $table = 'dashed__abandoned_cart_emails';

    protected $fillable = [
        'cart_id',
        'email',
        'email_number',
        'flow_step_id',
        'send_at',
        'sent_at',
        'clicked_at',
        'cancelled_at',
        'discount_code_id',
        'order_id',
        'converted_at',
    ];

    protected $casts = [
        'send_at' => 'datetime',
        'sent_at' => 'datetime',
        'clicked_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function flowStep(): BelongsTo
    {
        return $this->belongsTo(AbandonedCartFlowStep::class, 'flow_step_id');
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AbandonedCartClick::class, 'abandoned_cart_email_id');
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
