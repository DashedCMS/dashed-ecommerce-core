<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;

class ProductExtra extends Model
{
    use HasTranslations;
    use SoftDeletes;
    use LogsActivity;
    use HasCustomBlocks;

    protected static $logFillable = true;

    protected $fillable = [
        'product_id',
        'name',
        'type',
        'required',
    ];

    public $translatable = [
        'name',
    ];

    protected $table = 'dashed__product_extras';

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($productExtra) {
            foreach ($productExtra->productExtraOptions as $productExtraOption) {
                $productExtraOption->delete();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function product()
    {
        return $this->belongsto(Product::class);
    }

    public function productExtraOptions()
    {
        return $this->hasMany(ProductExtraOption::class);
    }
}
