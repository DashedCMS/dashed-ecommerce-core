<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Dashed\DashedCore\Models\Customsetting;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductFilter extends Model
{
    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'name',
        'hide_filter_on_overview_page',
    ];

    public $translatable = [
        'name',
    ];

    protected $table = 'dashed__product_filters';

    protected static function booted()
    {
        static::deleting(function ($productFilter) {
            foreach ($productFilter->productFilterOptions as $option) {
                $option->delete();
            }
        });
    }

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

    public function productFilterOptions()
    {
        return $this->hasMany(ProductFilterOption::class)->orderBy(Customsetting::get('product_filter_option_order_by', Sites::getActive(), 'order'), 'ASC');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_filter')->withPivot(['product_filter_option_id']);
    }
}
