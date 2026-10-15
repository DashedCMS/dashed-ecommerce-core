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
        'count_variants_separately' => 'boolean',
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
     * Het aantal waarmee deze korting rekent. Standaard tellen alle varianten
     * uit de productgroep samen (1x M + 1x L = 2 stuks, ook met verschillende
     * extra opties). Met count_variants_separately telt alleen de eigen regel.
     */
    public function countedQuantity(int $lineQuantity, ?int $groupQuantity = null): int
    {
        if ($this->count_variants_separately || $groupQuantity === null) {
            return $lineQuantity;
        }

        return max($lineQuantity, $groupQuantity);
    }

    /**
     * De hoogste staffel die geldt voor deze regel.
     *
     * @param  iterable<self>  $volumeDiscounts
     */
    public static function bestFor(iterable $volumeDiscounts, int $lineQuantity, ?int $groupQuantity = null): ?self
    {
        return collect($volumeDiscounts)
            ->filter(fn (self $discount) => (int) $discount->min_quantity <= $discount->countedQuantity($lineQuantity, $groupQuantity))
            ->sortByDesc('min_quantity')
            ->first();
    }

    /**
     * Prijs van een hele regel van $quantity stuks. Met apply_per_set telt de
     * korting alleen voor volle sets van min_quantity stuks: bij 1+1 (50% vanaf
     * 2) krijgen 3 stuks korting op 2 stuks en betaalt het derde de volle prijs.
     * Een vast bedrag (discount_price) is korting per stuk.
     *
     * $groupQuantity is het totaal over alle varianten in de winkelwagen. Bij
     * apply_per_set krijgt elke regel dan naar rato korting voor de volle sets
     * over dat totaal.
     */
    public function getLinePrice(float $lineTotal, int $quantity, ?int $groupQuantity = null): float
    {
        if ($quantity <= 0) {
            return (float) $this->getPrice($lineTotal);
        }

        $countedQuantity = $this->countedQuantity($quantity, $groupQuantity);

        $ratio = 1.0;
        if ($this->apply_per_set) {
            $setSize = max(1, (int) $this->min_quantity);
            $ratio = (intdiv($countedQuantity, $setSize) * $setSize) / $countedQuantity;
        }

        if ($this->type != 'percentage') {
            return max(0.0, $lineTotal - round((float) $this->discount_price * $quantity * $ratio, 2));
        }

        $discountedLineTotal = (float) $this->getPrice($lineTotal);
        if ($ratio >= 1.0) {
            return $discountedLineTotal;
        }

        $discount = round(($lineTotal - $discountedLineTotal) * $ratio, 2);

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
