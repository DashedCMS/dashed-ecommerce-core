<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function orderPayments()
    {
        $this->hasMany(OrderPayment::class);
    }

    public function pinTerminal(): BelongsTo
    {
        return $this->belongsTo(PinTerminal::class);
    }
}
