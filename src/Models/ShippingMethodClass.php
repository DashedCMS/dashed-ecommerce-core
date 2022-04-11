<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class ShippingMethodClass extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'shipping_method_id',
        'shipping_class_id',
        'costs',
    ];

    protected $table = 'qcommerce__shipping_method_class';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function shippingClass()
    {
        return $this->belongsTo(ShippingClass::class);
    }
}
