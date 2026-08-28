<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedEcommerceCore\Classes\TaxHelper;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductStockInformationJob;

class OrderProduct extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__order_products';

    protected $fillable = [
        'quantity',
        'returned_quantity',
        'is_pre_order',
        'order_id',
        'name',
        'product_id',
        'sku',
        'price',
        'discount',
        'btw',
        'vat_rate',
        'product_extras',
        'added_via',
    ];

    protected $casts = [
        'product_extras' => 'array',
        'hidden_options' => 'array',
        'returned_quantity' => 'integer',
        'skip_stock' => 'boolean',
    ];

    public function scopeSearch($query, ?string $search)
    {
        if (request()->get('search', $search)) {
            $search = strtolower(request()->get('search', $search));
            $query->where('name', 'LIKE', "%$search%")
                ->orWhereRaw('LOWER(product_extras) LIKE ?', ["%{$search}%"]);
        }
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($orderProduct) {
            $orderProduct->skip_stock = $orderProduct->resolveSkipStock();

            if ($orderProduct->product) {
                $orderProduct->vat_rate = $orderProduct->product->vat_rate;
                $orderProduct->btw = $orderProduct->price / (100 + ($orderProduct->vat_rate ?? 21)) * ($orderProduct->vat_rate ?? 21);
                $orderProduct->fulfillment_provider = $orderProduct->product->fulfillment_provider;
            } else {
                if (! $orderProduct->vat_rate && $orderProduct->btw > 0.00) {
                    $orderProduct->vat_rate = round($orderProduct->btw / ($orderProduct->price - $orderProduct->btw), 2) * 100;
                }
                if ($orderProduct->btw == 0.00 && $orderProduct->vat_rate > 0.00) {
                    $orderProduct->btw = $orderProduct->price / (100 + ($orderProduct->vat_rate ?? 21)) * ($orderProduct->vat_rate ?? 21);
                }
            }
        });

        static::saved(function ($orderProduct) {
            if ($orderProduct->product) {
                //                UpdateProductStockInformationJob::dispatch($orderProduct->product->productGroup, false)->onQueue('ecommerce');
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    /**
     * Laat minstens een van de gekozen productopties de voorraad met rust?
     *
     * Bewust een momentopname bij het aanmaken van de regel, geen live lookup:
     * afboeken en terugboeken moeten hetzelfde zeggen. Wordt de vlag op de
     * optie later omgezet, dan zou een live lookup bij annuleren voorraad
     * terugboeken die er nooit af is gegaan.
     */
    public function resolveSkipStock(): bool
    {
        $productExtras = $this->product_extras;

        if (! is_array($productExtras) || ! $productExtras) {
            return false;
        }

        // Extras zonder opties (invulveld, upload) staan in de JSON met een
        // id als 'product-extra-12' en hebben dus geen optie om op te zoeken.
        $optionIds = collect($productExtras)
            ->pluck('id')
            ->filter(fn ($id) => is_numeric($id))
            ->all();

        if (! $optionIds) {
            return false;
        }

        return ProductExtraOption::withTrashed()
            ->whereIn('id', $optionIds)
            ->where('skip_stock', 1)
            ->exists();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)
            ->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getPriceWithoutDiscountAttribute()
    {
        return $this->price + $this->discount;
    }

    public function getVatWithoutDiscountAttribute()
    {
        return TaxHelper::calculateTax($this->price + $this->discount, $this->vat_rate);
    }

    public function fulfillmentCompany(): BelongsTo
    {
        return $this->belongsTo(FulfillmentCompany::class, 'fulfillment_provider');
    }
}
