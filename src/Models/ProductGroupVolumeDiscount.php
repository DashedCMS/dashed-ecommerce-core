<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedCore\Traits\HasDynamicRelation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductGroupVolumeDiscount extends Model
{
    use SoftDeletes;
    use HasDynamicRelation;

    protected $table = 'dashed__product_group_volume_discounts';

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(function ($volumeDiscount) {
            $volumeDiscount->connectAllProducts();
        });
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'dashed__product_group_volume_discount_product', 'product_group_volume_discount_id', 'product_id');
    }

    public function connectAllProducts(): void
    {
        if ($this->active_for_all_variants) {
            $this->products()->sync($this->productGroup->products->pluck('id'));
        }
    }
}
