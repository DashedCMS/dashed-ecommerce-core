<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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


    public static function booted()
    {
        parent::booted();

        static::creating(function ($productExtra) {
            if ($productExtra->global) {
                $productExtra->order = ProductExtra::where('global', 1)->max('order') + 1;
            }
        });

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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'dashed__product_extra_product', 'product_extra_id', 'product_id');
    }

    public function productExtraOptions(): HasMany
    {
        return $this->hasMany(ProductExtraOption::class);
    }
}
