<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Dashed\DashedCore\Models\Customsetting;
use Spatie\Activitylog\Traits\LogsActivity;
use Dashed\DashedCore\Models\Concerns\HasSearchScope;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;

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
}
