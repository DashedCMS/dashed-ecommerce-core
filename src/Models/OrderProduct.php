<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Dashed\DashedEcommerceCore\Classes\TaxHelper;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductStockInformationJob;
use Dashed\DashedEcommerceCore\Database\Factories\OrderProductFactory;

class OrderProduct extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    /** Het volledige bestelde aantal was direct leverbaar op besteldatum. */
    public const STOCK_STATUS_IN_STOCK = 'in_stock';

    /** (Deels) backorder/pre-order: niet (volledig) leverbaar op besteldatum. */
    public const STOCK_STATUS_OUT_OF_STOCK = 'out_of_stock';

    protected static $logFillable = true;

    protected $table = 'dashed__order_products';

    protected $fillable = [
        'quantity',
        'returned_quantity',
        'is_pre_order',
        'stock_status_at_order',
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

    protected static function newFactory(): OrderProductFactory
    {
        return OrderProductFactory::new();
    }

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

            // Voorraadstatus op besteldatum: live productstand bij het aanmaken
            // van de regel (de voorraad wordt pas later in Order afgeboekt), dus
            // de stand nú = de stand op moment van bestellen. Niet overschrijven
            // als een aanroeper de waarde al expliciet heeft gezet.
            if ($orderProduct->stock_status_at_order === null) {
                $orderProduct->stock_status_at_order = $orderProduct->resolveStockStatusAtOrder();
            }

            // Zonder OSS op de actieve site blijft de order ongeladen: precies het oude pad.
            $order = OssVat::enabled() ? $orderProduct->order : null;
            $ossCountry = $order ? OssVat::destinationFor($order) : null;
            // Op een creditorder spiegelt de regel het tarief van de oorspronkelijke regel.
            $keepsGivenRate = $ossCountry && filled($order->credit_for_order_id) && $orderProduct->vat_rate !== null;

            if ($orderProduct->product) {
                if (! $keepsGivenRate) {
                    // Een product zonder tarief houdt null (btw via de 21-terugval), ook op een OSS-order.
                    $orderProduct->vat_rate = $ossCountry && $orderProduct->product->vat_rate !== null
                        ? OssVat::rateFor($ossCountry, (float) $orderProduct->product->vat_rate, $order->site_id)
                        : $orderProduct->product->vat_rate;
                }
                $orderProduct->btw = $orderProduct->price / (100 + ($orderProduct->vat_rate ?? 21)) * ($orderProduct->vat_rate ?? 21);
                $orderProduct->fulfillment_provider = $orderProduct->product->fulfillment_provider;
            } else {
                if (! $orderProduct->vat_rate && $orderProduct->btw > 0.00) {
                    $orderProduct->vat_rate = round($orderProduct->btw / ($orderProduct->price - $orderProduct->btw), 2) * 100;
                }

                if ($ossCountry && ! $keepsGivenRate && $orderProduct->vat_rate > 0.00) {
                    $ossRate = OssVat::rateFor($ossCountry, (float) $orderProduct->vat_rate, $order->site_id);
                    if (abs($ossRate - (float) $orderProduct->vat_rate) > 0.001) {
                        $orderProduct->vat_rate = $ossRate;
                        $orderProduct->btw = $orderProduct->price / (100 + $ossRate) * $ossRate;
                    }
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

    /**
     * Bepaalt of deze regel op het moment van bestellen (volledig) op voorraad
     * was. Definitie: groen alleen als het hele bestelde aantal direct leverbaar
     * was; een (deels) backorder/pre-order telt als niet op voorraad. Producten
     * zonder voorraadbeheer (`use_stock = false`) en regels zonder gekoppeld
     * product geven null (onbekend/niet van toepassing, niet getoond).
     *
     * Gebruikt dezelfde bron als de bestaande pre-order-check (`backorderedQuantity`),
     * zodat de waarde consistent is met `is_pre_order` en met de backfill.
     */
    public function resolveStockStatusAtOrder(): ?string
    {
        if (! $this->product_id) {
            return null;
        }

        $product = $this->relationLoaded('product')
            ? $this->product
            : Product::withTrashed()->find($this->product_id);

        if (! $product || ! $product->use_stock) {
            return null;
        }

        $quantity = (int) $this->quantity;

        // Backorder/pre-order (zelfde check als is_pre_order): niet het hele
        // bestelde aantal was direct leverbaar.
        if ($product->backorderedQuantity($quantity) > 0) {
            return self::STOCK_STATUS_OUT_OF_STOCK;
        }

        // Overige voorraadbeheer-producten (bv. handmatig toegevoegd ondanks
        // tekort): op voorraad als de rauwe on-hand-voorraad het bestelde
        // aantal dekte, anders niet.
        return $product->directSellableStock(true) >= $quantity
            ? self::STOCK_STATUS_IN_STOCK
            : self::STOCK_STATUS_OUT_OF_STOCK;
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
