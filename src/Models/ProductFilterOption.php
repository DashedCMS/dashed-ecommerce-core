<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductFilterOption extends Model
{
    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'product_filter_id',
        'order',
        'name',
    ];

    public $translatable = [
        'name',
    ];

    protected $table = 'dashed__product_filter_options';

    protected static function booted()
    {
        static::deleting(function ($filterOption) {
            $filterOption->products()->detach();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function productFilter()
    {
        return $this->belongsTo(ProductFilter::class);
    }

    public function products()
    {
        return $this->belongsToMany(ProductFilter::class, 'dashed__product_filter')->withPivot(['product_id']);
    }
}
