<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedCore\Traits\HasDynamicRelation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductGroupVolumeDiscount extends Model
{
    use SoftDeletes;
    use HasDynamicRelation;

    protected $table = 'dashed__product_group_volume_discounts';

    protected $casts = [
        'apply_per_set' => 'boolean',
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

    /**
     * Prijs van een hele regel van $quantity stuks. Met apply_per_set telt de
     * korting alleen voor volle sets van min_quantity stuks: bij 1+1 (50% vanaf
     * 2) krijgen 3 stuks korting op 2 stuks en betaalt het derde de volle prijs.
     * Een vast bedrag (discount_price) is korting per stuk.
     */
    public function getLinePrice(float $lineTotal, int $quantity): float
    {
        if ($quantity <= 0) {
            return (float) $this->getPrice($lineTotal);
        }

        $discountedQuantity = $quantity;
        if ($this->apply_per_set) {
            $setSize = max(1, (int) $this->min_quantity);
            $discountedQuantity = intdiv($quantity, $setSize) * $setSize;
        }

        if ($this->type != 'percentage') {
            return max(0.0, $lineTotal - (float) $this->discount_price * $discountedQuantity);
        }

        $discountedLineTotal = (float) $this->getPrice($lineTotal);
        if ($discountedQuantity === $quantity) {
            return $discountedLineTotal;
        }

        $discount = round(($lineTotal - $discountedLineTotal) * $discountedQuantity / $quantity, 2);

        return $lineTotal - $discount;
    }

    /**
     * Het kortingsbedrag op $price (niet de prijs na korting).
     */
    public function getDiscountedPrice($price, bool $formatResult = false): string|float
    {
        if ($this->type == 'percentage') {
            $discount = $price - ($price / 100 * (100 - $this->discount_percentage));
        } else {
            $discount = min((float) $price, (float) $this->discount_price);
        }

        return $formatResult ? CurrencyHelper::formatPrice($discount) : $discount;
    }

    public function getDiscountString(): string
    {
        if ($this->type == 'percentage') {
            return $this->discount_percentage . '%';
        } else {
            return CurrencyHelper::formatPrice($this->discount_price);
        }
    }
}
