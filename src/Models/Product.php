<?php

namespace Dashed\DashedEcommerceCore\Models;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use Filament\Forms\Get;
use Illuminate\Support\Facades\DB;
use Dashed\DashedPages\Models\Page;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Gloudemans\Shoppingcart\CartItem;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedCore\Traits\HasDynamicRelation;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Models\Concerns\IsVisitable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Dashed\LaravelLocalization\Facades\LaravelLocalization;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Events\Products\ProductSavedEvent;
use Dashed\DashedEcommerceCore\Events\Products\ProductCreatedEvent;
use Dashed\DashedEcommerceCore\Events\Products\ProductUpdatedEvent;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Products\ShowProduct;

class Product extends Model
{
    use SoftDeletes;
    use HasDynamicRelation;
    use IsVisitable;
    use HasCustomBlocks;

    protected $table = 'dashed__products';

    public $translatable = [
        'name',
        'slug',
        'short_description',
        'description',
        'search_terms',
        'product_search_terms',
        'content',
        'images',
    ];

    public $resourceRelations = [
        'productExtras' => [
            'childRelations' => [
                'productExtraOptions',
            ],
        ],
    ];

    protected $with = [
        'productFilters',
//        'parent',
//        'bundleProducts',
    ];

    protected $casts = [
        'site_ids' => 'array',
        'images' => 'array',
        'copyable_to_childs' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'expected_in_stock_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($product) {
            ProductCreatedEvent::dispatch($product);
        });

        static::updated(function ($product) {
            ProductUpdatedEvent::dispatch($product);
        });

        static::saved(function ($product) {
            if (! $product->is_bundle) {
                $product->bundleProducts()->detach();
            }

            $product->removeInvalidImages();

            ProductSavedEvent::dispatch($product);
            UpdateProductInformationJob::dispatch($product->productGroup)->onQueue('ecommerce');
        });

        static::deleting(function ($product) {
            $product->productCategories()->detach();
            $product->productFilters()->detach();
            $product->shippingClasses()->detach();
        });
    }

    public function productFilters()
    {
        return $this->belongsToMany(ProductFilter::class, 'dashed__product_filter')
            ->orderBy('created_at')
            ->withPivot(['product_filter_option_id']);
    }

    public function ecommerceActionLogs(): HasMany
    {
        return $this->hasMany(EcommerceActionLog::class);
    }

    public function scopeSearch($query, ?string $search = null)
    {
        $minPrice = request()->get('min-price') ? request()->get('min-price') : null;
        $maxPrice = request()->get('max-price') ? request()->get('max-price') : null;

        $search = request()->get('search') ?: $search;

        if ($minPrice) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice) {
            $query->where('price', '<=', $maxPrice);
        }

        $query->where(function ($query) use ($search) {
            $loop = 1;
            foreach (self::getTranslatableAttributes() as $attribute) {
                if (! method_exists($this, $attribute)) {
                    if ($loop == 1) {
                        $query->whereRaw('LOWER(`' . $attribute . '`) LIKE ? ', ['%' . trim(strtolower($search)) . '%']);
                    } else {
                        $query->orWhereRaw('LOWER(`' . $attribute . '`) LIKE ? ', ['%' . trim(strtolower($search)) . '%']);
                    }
                    $loop++;
                }
            }
            $query->orWhere('sku', $search)
                ->orWhere('ean', $search)
                ->orWhere('article_code', $search);
        });
    }

    public function scopePublic($query)
    {
        $query->where('public', 1);
    }

    public function scopeIndexable($query)
    {
        $query->where('indexable', 1);
    }

    public function scopeIsNotBundle($query)
    {
        $query->where('is_bundle', 0);
    }

    public function scopeIsBundle($query)
    {
        $query->where('is_bundle', 1);
    }

    public function scopePublicShowable($query, bool $overridePublic = false)
    {
        //        if (auth()->check() && auth()->user()->role == 'admin' && $overridePublic) {
        //            return;
        //        }

        $query
            ->public()
            ->thisSite();

        //        $query = $query->where(function ($query) {
        //            $query->where('start_date', null);
        //        })->orWhere(function ($query) {
        //            $query->where('start_date', '<=', Carbon::now());
        //        })->where(function ($query) {
        //            $query->where('end_date', null);
        //        })->orWhere(function ($query) {
        //            $query->where('end_date', '>=', Carbon::now());
        //        });

        return $query;
        //        }
    }

    public function scopePublicShowableWithIndex($query, bool $overridePublic = false)
    {
        $query
            ->public()
            ->thisSite()
            ->indexable();

        return $query;
    }

    public function scopeHandOrderShowable($query)
    {
        return;
    }

    public function scopeAvailableForShoppingFeed($query)
    {
        $query->where('ean', '!=', null);
    }

    public function breadcrumbs()
    {
        $breadcrumbs = [
            [
                'name' => $this->name,
                'url' => $this->getUrl(),
            ],
        ];

        if ($this->productGroup->products->count() > 1) {
            $breadcrumbs[] = [
                'name' => $this->productGroup->name,
                'url' => $this->productGroup->getUrl(),
            ];
        }

        $productCategory = $this->productCategories()->first();

        //Check if has child, to make sure all categories show in breadcrumbs
        while ($productCategory && $productCategory->getFirstChilds()->whereIn('id', $this->productCategories->pluck('id'))->first()) {
            $productCategory = $productCategory->getFirstChilds()->whereIn('id', $this->productCategories->pluck('id'))->first();
        }

        if ($productCategory) {
            while ($productCategory) {
                $breadcrumbs[] = [
                    'name' => $productCategory->name,
                    'url' => $productCategory->getUrl(),
                ];
                $productCategory = ProductCategory::find($productCategory->parent_id);
            }
        }

        $homePage = Page::isHome()->publicShowable()->first();
        if ($homePage) {
            $breadcrumbs[] = [
                'name' => $homePage->name,
                'url' => $homePage->getUrl(),
            ];
        }

        return array_reverse($breadcrumbs);
    }

    public function getCurrentPriceAttribute()
    {
        return $this->priceForUser();
        //        return $this->getRawOriginal('current_price');
    }

    public function getDiscountPriceAttribute(): ?float
    {
        //        return $this->discountPriceForUser();

        return $this->getRawOriginal('discount_price') > 0 ? $this->getRawOriginal('discount_price') : null;
    }

    /**
     * @deprecated Use firstImage attribute instead.
     */
    public function getFirstImageUrlAttribute()
    {
        throw new Exception('This method is deprecated. Use the firstImage attribute instead.');
        //        return $this->images[0] ?? '';
    }

    public function getFirstImageAttribute(): ?string
    {
        if (is_array($this->images) && count($this->images)) {
            return $this->images[0];
        } elseif ($this->productGroup && is_array($this->productGroup->images) && count($this->productGroup->images)) {
            return $this->productGroup->images[0];
        }

        return null;
    }

    /**
     * @deprecated You can now use the normal images array.
     */
    public function getAllImagesAttribute()
    {
        throw new Exception('This method is deprecated. Use the images attribute instead.');
        //        return $this->images ? collect($this->images) : collect();
    }

    /**
     * @deprecated Use the imagesExceptFirst attribute instead.
     */
    public function getAllImagesExceptFirstAttribute()
    {
        throw new Exception('This method is deprecated. Use the imagesExceptFirst attribute instead.');
        //        $images = $this->allImages;
        //        if (count($images)) {
        //            unset($images[0]);
        //        }
        //
        //        return $images;
    }

    public function getImagesExceptFirstAttribute(): array
    {
        $images = $this->images ?: [];
        if (count($images)) {
            unset($images[0]);
        }

        return $images;
    }

    public function getUrl($locale = null, $forceOwnUrl = false)
    {
        if (! $locale) {
            $locale = app()->getLocale();
        }

        return Cache::remember('product-' . $this->id . '-url-' . $locale . '-force-' . ($forceOwnUrl ? 'yes' : 'no'), 60 * 5, function () use ($locale, $forceOwnUrl) {
            if ($this->productGroup && $this->productGroup->only_show_parent_product && ! $forceOwnUrl) {
                return $this->productGroup->getUrl($locale);
            } else {
                $overviewPage = Product::getOverviewPage();
                if (! $overviewPage) {
                    return 'link-product-overview-page';
                }
                $url = $overviewPage->getUrl($locale) . '/' . $this->getTranslation('slug', $locale);
            }

            if ($locale != config('app.locale')) {
                $url = $locale . '/' . $url;
            }

            return LaravelLocalization::localizeUrl($url);
        });
    }

    public function getStatusAttribute()
    {
        if (! $this->public) {
            return false;
        } else {
            return true;
        }
    }

    public function reservedStock()
    {
        return OrderProduct::where('product_id', $this->id)->whereIn('order_id', Order::whereIn('status', ['pending'])->pluck('id'))->count();
    }

    public function stock()
    {
        return $this->total_stock - $this->reservedStock();
    }

    public function calculateStock()
    {
        $stock = 0;

        if ($this->is_bundle) {
            $minStock = 100000;
            foreach ($this->bundleProducts as $bundleProduct) {
                if ($bundleProduct->stock() < $minStock) {
                    $minStock = $bundleProduct->stock();
                }
            }

            $stock = $minStock;
        } elseif ($this->use_stock) {
            if ($this->outOfStockSellable()) {
                $stock = 100000;
            } else {
                $stock = $this->stock;
            }
        } else {
            if ($this->stock_status == 'in_stock') {
                $stock = 100000;
            } else {
                $stock = 0;
            }
        }

        $this->total_stock = $stock;
        $this->saveQuietly();
        $this->calculateInStock();
    }

    public function calculatePrices()
    {
        if ($this->is_bundle && $this->use_bundle_product_price) {
            $currentPrice = $this->bundleProducts()->sum('price');
        } else {
            $currentPrice = $this->price;
        }
        $this->current_price = $currentPrice;

        if ($this->is_bundle && $this->use_bundle_product_price) {
            $discountPrice = $this->bundleProducts()->sum('new_price');
        } else {
            if ($this->new_price) {
                $discountPrice = $this->new_price;
            } else {
                $discountPrice = null;
            }
        }

        $this->discount_price = $discountPrice;

        $discountFromGlobalDiscounts = 0;
        foreach (DiscountCode::isGlobalDiscount()->get() as $discountCode) {
            if ($discountCode->isValidForProduct($this)) {
                if ($discountCode->type == 'percentage') {
                    $discountFromGlobalDiscounts += $currentPrice / 100 * $discountCode->discount_percentage;
                } else {
                    $discountFromGlobalDiscounts += $discountCode->discount_amount;
                }
            }
        }

        if ($discountFromGlobalDiscounts > 0) {
            $this->discount_price = $currentPrice;
            $this->current_price = $currentPrice - $discountFromGlobalDiscounts;
        }
        if ($this->current_price < 0) {
            $this->current_price = 0.01;
        }

        $this->saveQuietly();

        foreach (User::where('has_custom_pricing', true)->get() as $user) {
            $productUser = DB::table('dashed__product_user')
                ->where('product_id', $this->id)
                ->where('user_id', $user->id)
                ->first();
            if ($productUser) {
                $productPrice = $this->current_price;
                if ($productUser->discount_price) {
                    $productPrice -= $productUser->discount_price;
                } elseif ($productUser->discount_percentage) {
                    $productPrice -= $productPrice / 100 * $productUser->discount_percentage;
                }

                if ($productPrice < 0) {
                    $productPrice = 0.01;
                }

                DB::table('dashed__product_user')
                    ->where('product_id', $this->id)
                    ->where('user_id', $user->id)
                    ->update([
                        'price' => $productPrice,
                    ]);
            }
        }
    }

    public function hasDirectSellableStock(): bool
    {
        if ($this->is_bundle) {
            $allBundleProductsDirectSellable = true;

            foreach ($this->bundleProducts as $bundleProduct) {
                if (! $bundleProduct->hasDirectSellableStock()) {
                    $allBundleProductsDirectSellable = false;
                }
            }

            if ($allBundleProductsDirectSellable) {
                return true;
            }
        } else {
            if ($this->directSellableStock() > 0) {
                return true;
            }
        }

        return false;
    }

    public function directSellableStock()
    {
        if ($this->use_stock) {
            return $this->stock - $this->reservedStock();

            return $this->stock();
        } else {
            if ($this->stock_status == 'in_stock') {
                return 100000;
            } else {
                return 0;
            }
        }
    }

    public function inStock(): bool
    {
        return $this->in_stock;
    }

    public function calculateInStock(): void
    {
        $inStock = false;

        if ($this->is_bundle) {
            $allBundleProductsInStock = true;

            foreach ($this->bundleProducts as $bundleProduct) {
                if (! $bundleProduct->inStock()) {
                    $allBundleProductsInStock = false;
                }
            }

            $inStock = $allBundleProductsInStock;
        } else {
            $inStock = $this->stock() > 0;
        }

        $this->in_stock = $inStock;
        $this->saveQuietly();
    }

    public function calculateTotalPurchases(): void
    {
        $purchases = $this->purchases;

        $this->total_purchases = $purchases;
        $this->saveQuietly();
    }

    public function calculateDeliveryTime(): void
    {
        if ($this->is_bundle) {
            $deliveryDays = 0;
            $deliveryDate = null;

            foreach ($this->bundleProducts as $bundleProduct) {
                if ($bundleProduct->inStock() && ! $bundleProduct->hasDirectSellableStock()) {
                    if ($bundleProduct->expectedDeliveryInDays() > $deliveryDays) {
                        $deliveryDays = $bundleProduct->expectedDeliveryInDays();
                    }
                    if ($bundleProduct->expectedInStockDate() && (! $deliveryDate || $bundleProduct->expectedInStockDate() > $deliveryDate)) {
                        $deliveryDate = $bundleProduct->expectedInStockDate();
                    }
                }
            }

            if ($deliveryDays && $deliveryDate && $deliveryDate <= now()->addDays($deliveryDays)) {
                $this->expected_delivery_in_days = $deliveryDays;
                $this->expected_in_stock_date = null;
            } elseif ($deliveryDays && $deliveryDate && $deliveryDate > now()->addDays($deliveryDays)) {
                $this->expected_in_stock_date = $deliveryDate;
                $this->expected_delivery_in_days = null;
            } elseif ($deliveryDays) {
                $this->expected_delivery_in_days = $deliveryDays;
                $this->expected_in_stock_date = null;
            } elseif ($deliveryDate) {
                $this->expected_in_stock_date = $deliveryDate;
                $this->expected_delivery_in_days = null;
            } else {
                $this->expected_in_stock_date = null;
                $this->expected_delivery_in_days = null;
            }
            $this->saveQuietly();
        }
    }

    public function outOfStockSellable(): bool
    {
        if (! $this->use_stock) {
            if ($this->stock_status == 'out_of_stock') {
                return false;
            }
        }

        if (! $this->out_of_stock_sellable) {
            return false;
        }

        if ((Customsetting::get('product_out_of_stock_sellable_date_should_be_valid', Sites::getActive(), 1) && ! $this->expectedInStockDateValid()) && ! $this->expectedDeliveryInDays()) {
            return false;
        }

        return true;
    }

    public function isPreorderable()
    {
        return $this->inStock() && ! $this->hasDirectSellableStock() && $this->use_stock;
    }

    public function expectedInStockDate()
    {
        return $this->expected_in_stock_date ? $this->expected_in_stock_date->format('d-m-Y') : null;
    }

    public function expectedInStockDateValid()
    {
        return $this->expected_in_stock_date >= now();
    }

    public function expectedInStockDateInWeeks(): float
    {
        $expectedInStockDate = self::expectedInStockDate();
        if (! $expectedInStockDate || Carbon::parse($expectedInStockDate) < now()) {
            return 0;
        }

        $diffInWeeks = floor(now()->subDay()->diffInWeeks(Carbon::parse($expectedInStockDate)));
        if ($diffInWeeks < 0) {
            $diffInWeeks = 0;
        }

        return $diffInWeeks;
    }

    public function expectedDeliveryInDays(): int
    {
        $expectedDeliveryInDays = $this->expected_delivery_in_days ?: 0;

        return $expectedDeliveryInDays;
    }

    public function purchasable()
    {
        if ($this->inStock() || $this->outOfStockSellable()) {
            return true;
        }

        return false;
    }

    public function parent()
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id')
            ->withTrashed();
    }

    public function productCategories()
    {
        return $this->belongsToMany(ProductCategory::class, 'dashed__product_category');
    }

    public function suggestedProducts()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_suggested_product', 'product_id', 'suggested_product_id');
    }

    public function crossSellProducts()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_crosssell_product', 'product_id', 'crosssell_product_id');
    }

    public function shippingClasses()
    {
        return $this->belongsToMany(ShippingClass::class, 'dashed__product_shipping_class');
    }

    public function tabs()
    {
        return $this->belongsToMany(ProductTab::class, 'dashed__product_tab_product', 'product_id', 'tab_id')
            ->orderBy('order');
    }

    public function globalTabs()
    {
        return $this->belongsToMany(ProductTab::class, 'dashed__product_tab_product', 'product_id', 'tab_id')
            ->where('global', 1);
    }

    public function ownTabs()
    {
        return $this->belongsToMany(ProductTab::class, 'dashed__product_tab_product', 'product_id', 'tab_id')
            ->where('global', 0);
    }

    public function faqs()
    {
        return $this->belongsToMany(ProductFaq::class, 'dashed__product_faq_product', 'product_id', 'faq_id')
            ->orderBy('order');
    }

    public function globalFaqs()
    {
        return $this->belongsToMany(ProductFaq::class, 'dashed__product_faq_product', 'product_id', 'faq_id')
            ->where('global', 1);
    }

    public function ownFaqs()
    {
        return $this->belongsToMany(ProductFaq::class, 'dashed__product_faq_product', 'product_id', 'faq_id')
            ->where('global', 0);
    }

    public function productCharacteristics()
    {
        return $this->hasMany(ProductCharacteristic::class);
    }

    public function allProductExtras(): ?Collection
    {
        $productExtraIds = [];

        $productExtraIds = array_merge($productExtraIds, $this->productExtras->pluck('id')->toArray());
        $productExtraIds = array_merge($productExtraIds, $this->globalProductExtras->pluck('id')->toArray());

        foreach ($this->productCategories as $productCategory) {
            $productExtraIds = array_merge($productExtraIds, $productCategory->globalProductExtras->pluck('id')->toArray());
        }

        if ($this->productGroup) {
            $productExtraIds = array_merge($productExtraIds, $this->productGroup->productExtras->pluck('id')->toArray());
            $productExtraIds = array_merge($productExtraIds, $this->productGroup->globalProductExtras->pluck('id')->toArray());
        }

        return ProductExtra::whereIn('id', $productExtraIds)
            ->orderBy('order')
            ->with(['ProductExtraOptions'])
            ->get();
    }

    public function allProductTabs(): ?Collection
    {
        $productTabIds = [];

        $productTabIds = array_merge($productTabIds, $this->tabs->pluck('id')->toArray());
        $productTabIds = array_merge($productTabIds, $this->globalTabs->pluck('id')->toArray());

        foreach ($this->productCategories as $productCategory) {
            $productTabIds = array_merge($productTabIds, $productCategory->globalTabs->pluck('id')->toArray());
        }

        if ($this->productGroup) {
            $productTabIds = array_merge($productTabIds, $this->productGroup->tabs->pluck('id')->toArray());
            $productTabIds = array_merge($productTabIds, $this->productGroup->globalTabs->pluck('id')->toArray());
        }

        return ProductTab::whereIn('id', $productTabIds)
            ->orderBy('order')
            ->get();
    }

    public function allProductFaqs(): ?Collection
    {
        $productFaqIds = [];

        $productFaqIds = array_merge($productFaqIds, $this->faqs->pluck('id')->toArray() ?? []);
        $productFaqIds = array_merge($productFaqIds, $this->globalFaqs->pluck('id')->toArray() ?? []);

        foreach ($this->productCategories as $productCategory) {
            $productFaqIds = array_merge($productFaqIds, $productCategory->globalFaqs->pluck('id')->toArray() ?? []);
        }

        //        if ($this->productGroup) {
        //            $productTabIds = array_merge($productTabIds, $this->productGroup->faqs->pluck('id')->toArray());
        //            $productTabIds = array_merge($productTabIds, $this->productGroup->globalFaqs->pluck('id')->toArray());
        //        }

        return ProductFaq::whereIn('id', $productFaqIds)
            ->orderBy('order')
            ->get();
    }

    public function productExtras(): HasMany
    {
        return $this->hasMany(ProductExtra::class)
            ->orderBy('order')
            ->with(['productExtraOptions']);
    }

    public function globalProductExtras(): BelongsToMany
    {
        return $this->belongsToMany(ProductExtra::class, 'dashed__product_extra_product', 'product_id', 'product_extra_id')
            ->where('global', 1)
            ->orderBy('order')
            ->with(['productExtraOptions']);
    }

    public function bundleProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'dashed__product_bundle_products', 'product_id', 'bundle_product_id');
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function showableCharacteristics($withoutIds = [])
    {
        return Cache::rememberForever("product-showable-characteristics-" . $this->id, function () use ($withoutIds) {
            $characteristics = [];

            $activeFilters = $this->productGroup->activeProductFilters;

            foreach ($activeFilters as $activeFilter) {
                $value = '';
                foreach ($activeFilter->productFilterOptions as $option) {
                    if ($this->productFilters()->where('product_filter_option_id', $option->id)->exists()) {
                        if ($value) {
                            $value .= ', ';
                        }
                        $value .= $option->name;
                    }
                }
                $characteristics[] = [
                    'name' => $activeFilter->name,
                    'value' => $value,
                ];
            }

            $allProductCharacteristics = ProductCharacteristics::orderBy('order')->get();
            foreach ($allProductCharacteristics as $productCharacteristic) {
                $thisProductCharacteristic = $this->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->first();
                if ($thisProductCharacteristic && $thisProductCharacteristic->value && ! $productCharacteristic->hide_from_public && ! in_array($productCharacteristic->id, $withoutIds)) {
                    $characteristics[] = [
                        'name' => $productCharacteristic->name,
                        'value' => $thisProductCharacteristic->value,
                    ];
                }
            }

            if ($this->weight) {
                $characteristics[] = [
                    'name' => Translation::get('weight', 'characteristics', 'Gewicht'),
                    'value' => Translation::get('weight-in-kg', 'characteristics', ':weight: kg', 'text', [
                        'weight' => $this->weight,
                    ]),
                ];
            }

            if ($this->width) {
                $characteristics[] = [
                    'name' => Translation::get('width', 'characteristics', 'Breedte'),
                    'value' => Translation::get('width-in-cm', 'characteristics', ':width: CM', 'text', [
                        'width' => $this->width,
                    ]),
                ];
            }

            if ($this->length) {
                $characteristics[] = [
                    'name' => Translation::get('length', 'characteristics', 'Lengte'),
                    'value' => Translation::get('length-in-cm', 'characteristics', ':length: CM', 'text', [
                        'length' => $this->length,
                    ]),
                ];
            }

            if ($this->height) {
                $characteristics[] = [
                    'name' => Translation::get('height', 'characteristics', 'Hoogte'),
                    'value' => Translation::get('height-in-cm', 'characteristics', ':height: CM', 'text', [
                        'height' => $this->height,
                    ]),
                ];
            }

            if ($this->sku) {
                $characteristics[] = [
                    'name' => Translation::get('sku', 'characteristics', 'SKU'),
                    'value' => $this->sku,
                ];
            }

            if ($this->ean) {
                $characteristics[] = [
                    'name' => Translation::get('ean', 'characteristics', 'EAN'),
                    'value' => $this->ean,
                ];
            }

            return $characteristics;
        });
    }

    public function allCharacteristics($withoutIds = [])
    {
        $characteristics = [];

        $activeFilters = $this->productGroup->activeProductFilters;

        foreach ($activeFilters as $activeFilter) {
            $value = '';
            foreach ($activeFilter->productFilterOptions as $option) {
                if ($this->productFilters()->where('product_filter_option_id', $option->id)->exists()) {
                    if ($value) {
                        $value .= ', ';
                    }
                    $value .= $option->name;
                }
            }
            $characteristics[] = [
                'name' => $activeFilter->name,
                'value' => $value,
            ];
        }

        $allProductCharacteristics = ProductCharacteristics::orderBy('order')->get();
        foreach ($allProductCharacteristics as $productCharacteristic) {
            $thisProductCharacteristic = $this->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->first();
            if ($thisProductCharacteristic && $thisProductCharacteristic->value && ! in_array($productCharacteristic->id, $withoutIds)) {
                $characteristics[] = [
                    'name' => $productCharacteristic->name,
                    'value' => $thisProductCharacteristic->value,
                ];
            }
        }

        return $characteristics;
    }

    public function getCharacteristicById(int|array $id): array
    {
        if (is_array($id)) {
            $productCharacteristic = $this->productCharacteristics()->whereIn('product_characteristic_id', $id)->get();
            $productCharacteristics = [];
            foreach ($productCharacteristic as $pCharacteristic) {
                $productCharacteristics[] = [
                    'name' => $pCharacteristic->productCharacteristic->name,
                    'value' => $pCharacteristic->value,
                ];
            }

            return $productCharacteristics;
        } else {
            $productCharacteristic = $this->productCharacteristics->where('product_characteristic_id', $id)->first();
            if ($productCharacteristic) {
                return [
                    'name' => $productCharacteristic->productCharacteristic->name,
                    'value' => $productCharacteristic->value,
                ];
            }
        }

        return [];
    }

    public function getSuggestedProducts(int $limit = 4, bool $random = true, $includeFromProductGroup = false): Collection
    {
        if ($includeFromProductGroup) {
            $suggestedProductIds = array_merge($this->suggestedProducts->pluck('id')->toArray(), $this->productGroup->suggestedProducts->pluck('id')->toArray());
        } else {
            $suggestedProductIds = $this->suggestedProducts->pluck('id')->toArray();
        }

        if (count($suggestedProductIds) < $limit) {
            $randomProductIds = Product::thisSite()
                ->publicShowableWithIndex()
                ->where('id', '!=', $this->id)
                ->whereNotIn('id', array_merge($suggestedProductIds, [$this->id]))
                ->limit($limit - count($suggestedProductIds))
                ->inRandomOrder()
                ->pluck('id')
                ->toArray();
            $suggestedProductIds = array_merge($randomProductIds, $suggestedProductIds);
        }

        $products = Product::thisSite()->publicShowable()->whereIn('id', $suggestedProductIds)->limit($limit);
        if ($random) {
            $products->inRandomOrder();
        }

        return $products->get();
    }

    public function getCrossSellProducts(bool $includeFromProductGroup = false, bool $removeIfAlreadyInCart = false): Collection
    {
        if ($includeFromProductGroup) {
            $crossSellProductsIds = array_merge($this->crossSellProducts->pluck('id')->toArray(), $this->productGroup->crossSellProducts->pluck('id')->toArray());
        } else {
            $crossSellProductsIds = $this->crossSellProducts->pluck('id')->toArray();
        }

        if ($removeIfAlreadyInCart) {
            foreach (ShoppingCart::cartItems() as $cartItem) {
                if ($cartItem->model && in_array($cartItem->model->id, $crossSellProductsIds)) {
                    $crossSellProductsIds = array_diff($crossSellProductsIds, [$cartItem->model->id]);
                }
            }
        }

        $products = Product::thisSite()->publicShowable()->whereIn('id', $crossSellProductsIds);

        return $products->get();
    }

    public static function getShoppingCartItemPrice(CartItem $cartItem, string|DiscountCode|null $discountCode = null)
    {
        if ($discountCode && is_string($discountCode)) {
            $discountCode = null;
        }

        $quantity = $cartItem->qty;
        $options = $cartItem->options['options'];

        $price = 0;

        $price += ((! $cartItem->model || ($cartItem->options['customProduct'] ?? false) || ($cartItem->options['isCustomPrice'] ?? false)) ? $cartItem->options['singlePrice'] : $cartItem->model->currentPrice) * $quantity;

        foreach ($options as $productExtraOptionId => $productExtraOption) {
            $optionId = null;
            $extraId = null;

            if (! str($productExtraOptionId)->contains('product-extra-')) {
                $optionId = $productExtraOptionId;
            } else {
                $extraId = str($productExtraOptionId)->explode('-')->last();
            }

            if ($optionId) {
                $quantity = $productExtraOption['quantity'] ?? $cartItem->qty;
                if ($quantity < 1) {
                    $quantity = 1;
                }
                if (! is_numeric($optionId)) {
                    continue;
                }
                $productExtraOptionId = (int)$optionId;

                if ($productExtraOptionId <= 0) {
                    continue;
                }
                $thisProductExtraOption = ProductExtraOption::find($productExtraOptionId);
                if ($thisProductExtraOption) {
                    if ($thisProductExtraOption->calculate_only_1_quantity) {
                        $price += $thisProductExtraOption->price;
                        $price += $thisProductExtraOption->productExtra->price;
                    } else {
                        $price += ($thisProductExtraOption->price * $quantity);
                        $price += ($thisProductExtraOption->productExtra->price * $quantity);
                    }
                }
            }
            if ($extraId) {
                $quantity = $cartItem->qty;
                if ($quantity < 1) {
                    $quantity = 1;
                }
                if (! is_numeric($extraId)) {
                    continue;
                }
                $productExtraId = (int)$extraId;

                if ($productExtraId <= 0) {
                    continue;
                }
                $thisProductExtra = ProductExtra::find($productExtraId);
                if ($thisProductExtra) {
                    $price += ($thisProductExtra->price * $quantity);
                }
            }

            //Should not be needed, otherwise it will count above items double
            //            if ($productExtraOption['value'] && ($productExtraOption['price'] ?? false)) {
            //                $price += $productExtraOption['price'] * $quantity;
            //            }
        }

        if ($cartItem->model && $cartItem->model->volumeDiscounts) {
            $volumeDiscount = $cartItem->model->volumeDiscounts()->where('min_quantity', '<=', $cartItem->qty)->orderBy('min_quantity', 'desc')->first();
            if ($volumeDiscount) {
                $price = $volumeDiscount->getPrice($price);
            }
        }

        if ($discountCode && $discountCode->type == 'percentage') {
            $discountValidForProduct = false;

            if ($discountCode->valid_for == 'categories') {
                if ($cartItem->model && $discountCode->productCategories()->whereIn('product_category_id', $cartItem->model->productCategories()->pluck('product_category_id'))->exists()) {
                    $discountValidForProduct = true;
                }
            } elseif ($discountCode->valid_for == 'products') {
                if ($cartItem->model && $discountCode->products()->where('product_id', $cartItem->model->id)->exists()) {
                    $discountValidForProduct = true;
                }
            } else {
                $discountValidForProduct = true;
            }

            if ($discountValidForProduct) {
                $price = round(($price / $quantity / 100) * (100 - $discountCode->discount_percentage), 2) * $quantity;
            }

            if ($price < 0) {
                $price = 0.01;
            }
        }

        return $price;
    }

    public static function stockFilamentSchema(): array
    {
        return [
            Toggle::make('use_stock')
                ->label('Voorraad bijhouden')
                ->reactive(),
            Toggle::make('limit_purchases_per_customer')
                ->label('Dit product mag maar een x aantal keer per bestelling gekocht worden')
                ->reactive(),
            Toggle::make('out_of_stock_sellable')
                ->label('Product doorverkopen wanneer niet meer op voorraad (pre-orders)')
                ->reactive()
                ->hidden(fn (Get $get) => ! $get('use_stock')),
            Toggle::make('low_stock_notification')
                ->label('Ik wil een melding krijgen als dit product laag op voorraad raakt')
                ->reactive()
                ->hidden(fn (Get $get) => ! $get('use_stock')),
            TextInput::make('stock')
                ->type('number')
                ->label('Hoeveel heb je van dit product op voorraad')
                ->helperText(fn ($record) => $record ? 'Er zijn er momenteel ' . $record->reservedStock() . ' gereserveerd' : '')
                ->maxValue(100000)
                ->required()
                ->numeric()
                ->hidden(fn (Get $get) => ! $get('use_stock')),
            DatePicker::make('expected_in_stock_date')
                ->label('Wanneer komt dit product weer op voorraad')
                ->reactive()
                ->helperText('Gebruik 1 van deze 2 opties')
                ->required(fn (Get $get) => ! $get('expected_delivery_in_days'))
                ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('out_of_stock_sellable')),
            TextInput::make('expected_delivery_in_days')
                ->label('Levering in dagen')
                ->helperText('Hoeveel dagen duurt het voordat dit product geleverd kan worden?')
                ->reactive()
                ->numeric()
                ->minValue(1)
                ->maxValue(1000)
                ->required(fn (Get $get) => ! $get('expected_in_stock_date') && $get('out_of_stock_sellable')),
            TextInput::make('low_stock_notification_limit')
                ->label('Lage voorraad melding')
                ->helperText('Als de voorraad van dit product onder onderstaand nummer komt, krijg je een melding')
                ->type('number')
                ->reactive()
                ->required()
                ->minValue(1)
                ->maxValue(100000)
                ->default(1)
                ->numeric()
                ->hidden(fn (Get $get) => ! $get('use_stock') || ! $get('low_stock_notification')),
            Select::make('stock_status')
                ->label('Is dit product op voorraad')
                ->options([
                    'in_stock' => 'Op voorraad',
                    'out_of_stock' => 'Uitverkocht',
                ])
                ->default('in_stock')
                ->required()
                ->hidden(fn (Get $get) => $get('use_stock')),
            TextInput::make('limit_purchases_per_customer_limit')
                ->type('number')
                ->label('Hoeveel mag dit product gekocht worden per bestelling')
                ->minValue(1)
                ->maxValue(100000)
                ->default(1)
                ->required()
                ->numeric()
                ->hidden(fn (Get $get) => ! $get('limit_purchases_per_customer')),
            Select::make('fulfillment_provider')
                ->label('Door wie wordt dit product verstuurd?')
                ->helperText('Laat leeg voor eigen fulfillment')
                ->options(function () {
                    $options = [];

                    foreach (ecommerce()->builder('fulfillmentProviders') as $key => $provider) {
                        $options[$key] = $provider['name'];
                    }
                    foreach (FulfillmentCompany::all() as $fulfillmentCompany) {
                        $options[$fulfillmentCompany->id] = $fulfillmentCompany->name;
                    }

                    return $options;
                }),
        ];
    }

    public static function canHaveParent(): bool
    {
        return false;
    }

    public static function returnForRoute(): array
    {
        return [
            'livewireComponent' => cms()->class('showProduct') ?: ShowProduct::class,
        ];
    }

    public function volumeDiscounts(): BelongsToMany
    {
        return $this->belongsToMany(ProductGroupVolumeDiscount::class, 'dashed__product_group_volume_discount_product', 'product_id', 'product_group_volume_discount_id')
            ->orderBy('min_quantity');
    }

    public function priceForUser(?User $user = null, bool $fillFromProduct = true)
    {
        if (! $user && auth()->check()) {
            $user = auth()->user();
        }

        if ($user && $user->has_custom_pricing) {
            return DB::table('dashed__product_user')
                ->where('user_id', $user->id)
                ->where('product_id', $this->id)
                ->value('price') ?? ($fillFromProduct ? $this->getRawOriginal('current_price') : null);
        }

        return ($fillFromProduct ? $this->getRawOriginal('current_price') : null);
    }

    public function discountPriceForUser(?User $user = null): ?float
    {
        if (! $user && auth()->check()) {
            $user = auth()->user();
        }

        //        if ($user) {
        //            return DB::table('dashed__product_user')
        //                ->where('user_id', $user->id)
        //                ->where('product_id', $this->id)
        //                ->value('discount_price') ?? ($fillFromProduct ? $this->getRawOriginal('discount_price') : null);
        //        }

        return $this->getRawOriginal('discount_price');
    }

    public function showProductGroup(): bool
    {
        return $this->productGroup->only_show_parent_product ?? false;
    }

    public function fulfillmentCompany(): BelongsTo
    {
        return $this->belongsTo(FulfillmentCompany::class, 'fulfillment_provider');
    }

    public function removeInvalidImages(): void
    {
        foreach (Locales::getActivatedLocalesFromSites() as $locale) {
            $images = $this->getTranslation('images', $locale);
            if (is_array($images)) {
                foreach ($images as $key => $image) {
                    if (! mediaHelper()->getSingleMedia($image, 'original')) {
                        unset($images[$key]);
                    }
                }
                $images = array_values($images);
                $this->setTranslation('images', $locale, $images);
            }
        }
        $this->saveQuietly();
    }

    public function getImagesToShowAttribute(): array
    {
        $images = is_array($this->images) ? $this->images : [];

        if ($this->is_bundle && Customsetting::get('product_bundle_show_bundle_product_images', Sites::getActive(), 0)) {
            foreach ($this->bundleProducts as $bundleProduct) {
                $images = array_merge($images, $bundleProduct->imagesToShow);
            }
        }

        if (is_array($this->productGroup->images) && count($this->productGroup->images)) {
            foreach ($this->productGroup->images as $image) {
                if (! in_array($image, $images)) {
                    $images[] = $image;
                }
            }
        }

        return $images;
    }

    public function getOriginalImagesToShowAttribute(): array
    {
        $images = [];

        foreach ($this->imagesToShow as $image) {
            $images[] = mediaHelper()->getSingleMedia($image, 'original')->url ?? '';
        }

        return $images;
    }

    public function replaceContentVariables(?string $content, array $filters = []): ?string
    {
        $variables = [
            'name' => $this->name,
        ];

        foreach ($filters as $filterKey => $filter) {
            $filterName = str($filter['name'])->lower()->toString();
            if ($filter['active'] && collect($filter['options'])->where('id', $filter['active'])->count()) {
                $variables[$filterName] = collect($filter['options'])->where('id', $filter['active'])->first()['name'];
            } else {
                $variables[$filterName] = implode(', ', collect($filter['options'])->pluck('name')->toArray());
            }
        }

        foreach ($variables as $key => $value) {
            if (is_array($content)) {
                $content = array_map(function ($item) use ($key, $value) {
                    return str_replace(':' . $key . ':', $value, $item);
                }, $content);
            } else {
                $content = str_replace(':' . $key . ':', $value, $content);
            }
        }

        return $content;
    }

    public function disabledShippingMethods(): BelongsToMany
    {
        return $this->belongsToMany(ShippingMethod::class, 'dashed__shipping_method_disabled_products', 'product_id', 'shipping_method_id');
    }
}
