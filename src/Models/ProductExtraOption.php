<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductExtraOption extends Model
{
    use HasTranslations;
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'product_extra_id',
        'value',
        'price',
        'calculate_only_1_quantity',
    ];

    public $translatable = [
        'value',
    ];

    protected $table = 'dashed__product_extra_options';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function productExtra()
    {
        return $this->belongsto(ProductExtra::class);
    }
}
