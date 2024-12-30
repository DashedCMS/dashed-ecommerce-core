<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedPages\Models\Page;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedCore\Traits\HasDynamicRelation;
use Dashed\DashedCore\Models\Concerns\IsVisitable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\ShowProduct;

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

    public function getPrice($price, bool $formatResult = false): string|float
    {
        $price -= $this->getDiscountedPrice($price, false);
        return $formatResult ? CurrencyHelper::formatPrice($price) : $price;
    }

    public function getDiscountedPrice($price, bool $formatResult = false): string|float
    {
        $discountedPrice = $price;
        if ($this->type == 'percentage') {
            $discountedPrice = $discountedPrice - ($discountedPrice / 100 * (100 - $this->discount_percentage));
        } else {
            $discountedPrice = $discountedPrice - $this->discount_amount;
        }
        return $formatResult ? CurrencyHelper::formatPrice($discountedPrice) : $discountedPrice;
    }

    public function getDiscountString(): string
    {
        if ($this->type == 'percentage') {
            return $this->discount_percentage . '%';
        } else {
            return CurrencyHelper::formatPrice($this->discount_amount);
        }
    }
}
