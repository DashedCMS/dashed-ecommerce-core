<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductCharacteristics extends Model
{
    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'name',
        'order',
        'hide_from_public',
    ];

    public $translatable = [
        'name',
    ];

    protected $table = 'dashed__product_characteristics';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function scopeSearch($query)
    {
        if (request()->get('search')) {
            $search = strtolower(request()->get('search'));
            $query->where('name', 'LIKE', "%$search%");
        }
    }

    public function productCharacteristic()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_characteristic', 'product_characteristic_id');
    }

    public function product()
    {
        return $this->belongsto(Product::class);
    }
}
