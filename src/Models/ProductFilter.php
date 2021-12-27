<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Qubiqx\QcommerceCore\Classes\Sites;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Qubiqx\QcommerceCore\Models\Customsetting;

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

    protected $table = 'qcommerce__product_filters';

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
        return $this->belongsToMany(Product::class, 'qcommerce__product_filter')->withPivot(['product_filter_option_id']);
    }
}
