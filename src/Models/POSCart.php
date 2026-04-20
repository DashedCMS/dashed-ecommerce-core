<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class POSCart extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__pos_carts';

    protected $casts = [
        'products' => 'array',
        'custom_fields' => 'array',
        'prices_ex_vat' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logExcept(['products', 'custom_fields']);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
