<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductTab extends Model
{
    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'name',
        'content',
    ];

    public $translatable = [
        'name',
        'content',
    ];

    protected $table = 'dashed__product_tabs';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public static function booted()
    {
        static::creating(function ($productTab) {
            if ($productTab->global) {
                $productTab->order = ProductTab::where('global', 1)->max('order') + 1;
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'dashed__product_tab_product', 'tab_id', 'product_id');
    }

    public function productCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'dashed__product_tab_product_category', 'product_tab_id', 'product_category_id');
    }
}
