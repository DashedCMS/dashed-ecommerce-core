<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Dashed\DashedCore\Models\Customsetting;
use Spatie\Activitylog\Traits\LogsActivity;
use Dashed\DashedCore\Models\Concerns\HasSearchScope;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;

class ProductFilter extends Model
{
    use HasTranslations;
    use LogsActivity;
    use HasSearchScope;
    use HasCustomBlocks;

    protected static $logFillable = true;

    protected $fillable = [
        'name',
        'hide_filter_on_overview_page',
    ];

    public $translatable = [
        'name',
    ];

    protected $table = 'dashed__product_filters';

    protected $with = [
        'productFilterOptions'
    ];

    protected static function booted()
    {
        static::deleting(function ($productFilter) {
            foreach ($productFilter->productFilterOptions as $option) {
                $option->delete();
            }
            DB::table('dashed__active_product_filter')->where('product_filter_id', $productFilter->id)->delete();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
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
