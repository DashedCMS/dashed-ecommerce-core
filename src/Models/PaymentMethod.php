<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Qubiqx\QcommerceCore\Classes\Sites;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasTranslations;
    use LogsActivity;
    use SoftDeletes;

    protected static $logFillable = true;

    protected $fillable = [
        'site_id',
        'name',
        'additional_info',
        'payment_instructions',
        'extra_costs',
        'available_from_amount',
        'deposit_calculation',
        'postpay',
        'psp',
        'psp_id',
        'image',
        'active',
        'deposit_calculation_payment_method_ids',
    ];

    public $translatable = [
        'name',
        'additional_info',
        'payment_instructions',
    ];

    protected $casts = [
        'deposit_calculation_payment_method_ids' => 'array',
    ];

    protected $table = 'qcommerce__payment_methods';

    public function orderPayments()
    {
        $this->hasMany(OrderPayment::class);
    }
}
