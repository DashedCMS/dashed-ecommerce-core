<?php

namespace Dashed\DashedEcommerceCore\Models;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class POSCart extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__pos_carts';

    protected $casts = [
        'products' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
