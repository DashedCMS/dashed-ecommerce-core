<?php

namespace Dashed\DashedEcommerceCore\Models;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    protected $table = 'dashed__carts';

    protected $fillable = [
        'user_id',
        'token',
        'type',
        'locale',
        'currency',
        'store_id',
        'discount_code_id',
        'shipping_method_id',
        'shipping_zone_id',
        'payment_method_id',
        'deposit_payment_method_id',
        'meta',
        'abandoned_email',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'msclkid',
        'landing_page',
        'landing_page_referrer',
        'attribution_first_touch_at',
        'attribution_last_touch_at',
        'attribution_extra',
    ];

    protected $casts = [
        'meta' => 'array',
        'prices_ex_vat' => 'boolean',
        'attribution_extra' => 'array',
        'attribution_first_touch_at' => 'datetime',
        'attribution_last_touch_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Cart $cart) {
            if (! $cart->token) {
                $cart->token = (string) Str::uuid();
            }
            if (! $cart->type) {
                $cart->type = 'default';
            }
        });

        static::created(function (Cart $cart) {
            // Vang attributie op zodra de cart wordt aangemaakt.
            try {
                \Dashed\DashedEcommerceCore\Services\Attribution\AttributionTracker::attachToCart($cart);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CartLog::class)->orderBy('created_at');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
        // als DiscountCode elders staat: pas namespace aan
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function depositPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'deposit_payment_method_id');
    }

    public function updateTotal(): void
    {
        $this->total = $this->items()->sum(\DB::raw('unit_price * quantity'));
        $this->saveQuietly();
    }
}
