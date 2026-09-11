<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PaymentMethod extends Model
{
    use HasTranslations;
    use LogsActivity;
    use SoftDeletes;

    protected static $logFillable = true;

    public $translatable = [
        'name',
        'additional_info',
        'payment_instructions',
    ];

    protected $casts = [
        'deposit_calculation_payment_method_ids' => 'array',
    ];

    protected $table = 'dashed__payment_methods';

    public static function booted()
    {
        static::creating(function ($paymentMethod) {
            $paymentMethod->order = PaymentMethod::max('order') + 1;
        });

        // Een betaalmethode met psp own kost niets en loopt langs geen PSP:
        // in een checkout is dat de ingang voor orders van een cent die
        // daarna handmatig op betaald gezet worden. Alleen actief als het
        // project dat uitdrukkelijk toestaat (DASHED_ALLOW_OWN_PSP_IN_CHECKOUT).
        static::saving(function (PaymentMethod $paymentMethod) {
            if ($paymentMethod->psp === 'own' && $paymentMethod->type === 'online' && $paymentMethod->active && ! config('dashed-ecommerce-core.security.allow_own_psp_in_checkout', true)) {
                $paymentMethod->active = false;

                \Illuminate\Support\Facades\Log::warning('Betaalmethode met psp own mag niet actief zijn in de checkout; automatisch uitgezet.', [
                    'payment_method_id' => $paymentMethod->id,
                    'name' => $paymentMethod->name,
                    'user_id' => auth()->id(),
                ]);
            }
        });

        parent::booted();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function orderPayments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dashed__payment_method_users', 'payment_method_id', 'user_id');
    }

    public function shippingMethods(): BelongsToMany
    {
        return $this->belongsToMany(ShippingMethod::class, 'dashed__payment_method_shipping_method', 'payment_method_id', 'shipping_method_id');
    }

    public function pinTerminal(): BelongsTo
    {
        return $this->belongsTo(PinTerminal::class);
    }
}
