<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Dashed\DashedEcommerceCore\Classes\TaxHelper;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductStockInformationJob;
use Dashed\DashedEcommerceCore\Events\Orders\OrderProductProductionReady;
use Dashed\DashedEcommerceCore\Events\Orders\OrderProductProductionNotReady;
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
        'production_ready_at' => 'datetime',
        'production_ready_by' => 'integer',
    ];

    protected static function newFactory(): OrderProductFactory
    {
        return OrderProductFactory::new();
    }

    /**
     * Doorzoekbare tekst van de gekozen extra's (namen + waarden) als SQL-
     * expressie. Op MySQL via JSON_EXTRACT + CAST zodat ge-escapete tekens
     * (bijv. \u00e9) als gewone tekst matchen, zonder hoofdlettergevoeligheid; elders de ruwe JSON-kolom.
     */
    public static function extrasSearchSql(string $column = 'product_extras'): string
    {
        $driver = (new static())->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // JSON naar tekst krijgt utf8mb4_bin (hoofdlettergevoelig); forceer
            // een ci-collatie zodat zoeken net zo werkt als op de naam.
            return "(CASE WHEN JSON_VALID({$column}) THEN CONVERT(JSON_EXTRACT({$column}, '\$[*].name', '\$[*].value') USING utf8mb4) COLLATE utf8mb4_unicode_ci ELSE CONVERT({$column} USING utf8mb4) COLLATE utf8mb4_unicode_ci END)";
        }

        return $column;
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

        // Een gekozen optie die de voorraad met rust laat (skip_stock) wordt
        // voor de klant gemaakt, bijvoorbeeld een aparte kleur: dat exemplaar
        // komt nooit van de plank, ook al ligt het product wel op voorraad.
        if ($this->skip_stock) {
            return self::STOCK_STATUS_OUT_OF_STOCK;
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

    public function productionReadyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'production_ready_by');
    }

    public function isProductionReady(): bool
    {
        return $this->production_ready_at !== null;
    }

    /**
     * Meld de regel als klaar (geprint/geproduceerd). Idempotent: een regel
     * die al klaar is houdt zijn tijd en melder en vuurt geen tweede event.
     */
    public function markProductionReady(?User $by = null): bool
    {
        if ($this->isProductionReady()) {
            return false;
        }

        $by ??= self::currentProductionUser();
        $at = now();

        // Conditional update so a double tap or app + CMS at the same time
        // only changes the row (and logs/dispatches) once.
        $changed = static::query()
            ->whereKey($this->getKey())
            ->whereNull('production_ready_at')
            ->update([
                'production_ready_at' => $at,
                'production_ready_by' => $by?->id,
                'updated_at' => $at,
            ]);

        if ($changed !== 1) {
            $this->refreshProductionState();

            return false;
        }

        $this->forceFill([
            'production_ready_at' => $at,
            'production_ready_by' => $by?->id,
            'updated_at' => $at,
        ])->syncOriginal();
        $this->setRelation('productionReadyBy', $by);

        OrderLog::createLog(
            orderId: $this->order_id,
            tag: 'order.product.production-ready',
            note: $this->name . ' is klaar gemeld',
            userId: $by?->id,
        );

        OrderProductProductionReady::dispatch($this, $by);

        return true;
    }

    /**
     * Zet de regel terug op niet klaar. Idempotent: een regel die niet klaar
     * is blijft ongemoeid en vuurt geen event.
     */
    public function markProductionNotReady(?User $by = null): bool
    {
        if (! $this->isProductionReady()) {
            return false;
        }

        $by ??= self::currentProductionUser();
        $at = now();

        $changed = static::query()
            ->whereKey($this->getKey())
            ->whereNotNull('production_ready_at')
            ->update([
                'production_ready_at' => null,
                'production_ready_by' => null,
                'updated_at' => $at,
            ]);

        if ($changed !== 1) {
            $this->refreshProductionState();

            return false;
        }

        $this->forceFill([
            'production_ready_at' => null,
            'production_ready_by' => null,
            'updated_at' => $at,
        ])->syncOriginal();
        $this->setRelation('productionReadyBy', null);

        OrderLog::createLog(
            orderId: $this->order_id,
            tag: 'order.product.production-not-ready',
            note: $this->name . ' is weer op niet klaar gezet',
            userId: $by?->id,
        );

        OrderProductProductionNotReady::dispatch($this, $by);

        return true;
    }

    /**
     * Vaste vorm van de printstatus voor API-payloads.
     *
     * @return array{production_ready: bool, production_ready_at: ?string, production_ready_by: ?array{id: int, name: ?string, avatar_url: ?string}}
     */
    public function productionReadyPayload(): array
    {
        $user = $this->production_ready_by ? $this->productionReadyBy : null;

        return [
            'production_ready' => $this->isProductionReady(),
            'production_ready_at' => $this->production_ready_at?->toIso8601String(),
            'production_ready_by' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url ?? null,
            ] : null,
        ];
    }

    /** Sync a stale in-memory model with the stored print status after a lost race. */
    private function refreshProductionState(): void
    {
        $fresh = static::query()->whereKey($this->getKey())->first(['production_ready_at', 'production_ready_by']);

        if (! $fresh) {
            return;
        }

        $this->setRawAttributes(array_merge($this->getAttributes(), $fresh->getAttributes()), true);
        $this->unsetRelation('productionReadyBy');
    }

    private static function currentProductionUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
