<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCharacteristic extends Model
{
    use HasTranslations;
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'value',
        'order',
    ];

    public $translatable = [
        'value',
    ];

    protected $table = 'dashed__product_characteristic';

    protected static function booted()
    {
        static::deleting(function ($productCharacteristic) {
            $productCharacteristic->productCharacteristic()->detach();
            ProductCharacteristic::where('product_characteristic_id', $productCharacteristic->id)->delete();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productCharacteristic()
    {
        return $this->belongsTo(ProductCharacteristics::class);
    }
}
