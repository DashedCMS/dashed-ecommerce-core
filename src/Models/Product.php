<?php

namespace Dashed\DashedEcommerceCore\Models;

use Carbon\Carbon;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedPages\Models\Page;
use Illuminate\Support\Facades\App;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\View;
use Gloudemans\Shoppingcart\CartItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedCore\Traits\HasDynamicRelation;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Models\Concerns\IsVisitable;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Events\Products\ProductCreatedEvent;
use Dashed\DashedEcommerceCore\Events\Products\ProductUpdatedEvent;

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
        'content',
        'images',
        'productCharacteristics',
        'productExtras',
    ];

    protected $with = [
//        'productFilters',
//        'parent',
//        'bundleProducts',
//        'childProducts',
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
            if ($product->is_bundle && $product->type == 'variable' && ! $product->parent_id) {
                $product->is_bundle = false;
                $product->save();
                $product->bundleProducts()->detach();
            }

            if ($product->childProducts()->count()) {
                $product->parent_id = null;
                $product->saveQuietly();
            }


            foreach(Locales::getLocalesArray() as $locale) {
                Cache::forget('product-' . $product->id . '-url-' . $locale['id']);
            }
            UpdateProductInformationJob::dispatch($product);
        });

        static::deleting(function ($product) {
            foreach ($product->childProducts as $childProduct) {
                $childProduct->delete();
            }
            $product->productCategories()->detach();
            $product->productFilters()->detach();
            $product->activeProductFilters()->detach();
        });
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
        });
    }

    public function scopePublic($query)
    {
        $query->where('public', 1);
    }

    public function scopeIsNotBundle($query)
    {
        $query->where('is_bundle', 0);
    }

    public function scopeIsBundle($query)
    {
        $query->where('is_bundle', 1);
    }

    public function scopeNotParentProduct($query)
    {
        $query->where(function ($query) {
            $query->where('type', '!=', 'variable');
        })->orWhere(function ($query) {
            $query->where('type', 'variable')
                ->where('parent_id', '!=', null);
        });
    }

    public function scopePublicShowable($query, bool $overridePublic = false)
    {
        if (auth()->check() && auth()->user()->role == 'admin' && $overridePublic) {
            return;
        }
        //        if (auth()->guest() || (auth()->check() && auth()->user()->role !== 'admin' && $overridePublic)) {
        $query
            ->public()
            ->thisSite()
            ->where(function ($query) {
                $query
                    ->where('sku', '!=', null)
                    ->where('price', '!=', null);
            })
            ->orWhere(function ($query) {
                $query
                    ->where('type', 'variable')
                    ->where('parent_id', null);
            });

        if (! Customsetting::get('product_use_simple_variation_style', null, false)) {
            $query = $query->notParentProduct();
        }

        $query = $query->where(function ($query) {
            $query->where('start_date', null);
        })->orWhere(function ($query) {
            $query->where('start_date', '<=', Carbon::now());
        })->where(function ($query) {
            $query->where('end_date', null);
        })->orWhere(function ($query) {
            $query->where('end_date', '>=', Carbon::now());
        });
        //        }
    }

    public function scopeHandOrderShowable($query)
    {
        $query
            ->where(function ($query) {
                $query->where('type', '!=', 'variable')
                    ->where('sku', '!=', null)
                    ->where('price', '!=', null)
                    ->public();
            })->orWhere(function ($query) {
                $query->where('type', 'variable')
                    ->where('parent_id', '!=', null)
                    ->where('sku', '!=', null)
                    ->where('price', '!=', null)
                    ->public();
            })->where(function ($query) {
                $query->where('start_date', null);
            })->orWhere(function ($query) {
                $query->where('start_date', '<=', Carbon::now());
            })->where(function ($query) {
                $query->where('end_date', null);
            })->orWhere(function ($query) {
                $query->where('end_date', '>=', Carbon::now());
            });
    }

    public function scopeTopLevel($query)
    {
        $query->where('parent_id', null);
    }

    public function scopeAvailableForShoppingFeed($query)
    {
        $query->where('ean', '!=', null)->whereIn('type', ['simple', 'variable']);
    }

    public function scopePushableToEfulfillmentShop($query)
    {
        $query->where('ean', '!=', null)->whereIn('type', ['simple', 'variable'])->where('efulfillment_shop_id', null)->thisSite();
    }

    public function breadcrumbs()
    {
        $breadcrumbs = [
            [
                'name' => $this->name,
                'url' => $this->getUrl(),
            ],
        ];

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
        if ($this->is_bundle && $this->use_bundle_product_price) {
            return $this->bundleProducts()->sum('price');
        } elseif ($this->childProducts()->count()) {
            return $this->childProducts()->orderBy('price', 'ASC')->value('price');
        } else {
            return $this->price;
        }
    }

    public function getDiscountPriceAttribute()
    {
        if ($this->is_bundle && $this->use_bundle_product_price) {
            return $this->bundleProducts()->sum('new_price');
        } elseif ($this->childProducts()->count()) {
            return $this->childProducts()->orderBy('price', 'ASC')->value('new_price');
        } else {
            if ($this->new_price) {
                return $this->new_price;
            } else {
                return null;
            }
        }
    }

    public function getFirstImageUrlAttribute()
    {
        return $this->allImages->first()['image'] ?? '';
    }

    public function getAllImagesAttribute()
    {
        return $this->images ? collect($this->images) : collect();
    }

    public function getAllImagesExceptFirstAttribute()
    {
        $images = $this->allImages;
        if (count($images)) {
            unset($images[0]);
        }

        return $images;
    }

    public function getUrl($locale = null)
    {
        if (! $locale) {
            $locale = app()->getLocale();
        }

        return Cache::tags(['product-' . $this->id])->remember('product-' . $this->id . '-url-' . $locale, 60 * 5, function () use ($locale) {
            if (! $locale) {
                $locale = App::getLocale();
            }

            if (! Customsetting::get('product_use_simple_variation_style', null, false) && $this->childProducts()->count()) {
                foreach ($this->childProducts as $childProduct) {
                    if ($childProduct->inStock() && ! isset($url)) {
                        $url = $childProduct->getUrl();
                    }
                }
                if (! isset($url)) {
                    $url = $this->childProducts()->first()->getUrl();
                }
            } elseif ($this->parent && $this->parent->only_show_parent_product) {
                return $this->parent->getUrl();
            } else {
                $url = '/' . Translation::get('products-slug', 'slug', 'products') . '/' . $this->slug;
            }

            if ($locale != config('app.locale')) {
                $url = App::getLocale() . '/' . $url;
            }

            return LaravelLocalization::localizeUrl($url);
        });
    }

    public function getStatusAttribute()
    {
        if (! $this->public) {
            return false;
        }

        if ($this->type == 'variable' && ! $this->parent_id) {
            return true;
        }

        $active = false;
        if (! $this->start_date && ! $this->end_date) {
            $active = true;
        } else {
            if ($this->start_date && $this->end_date) {
                if ($this->start_date <= Carbon::now() && $this->end_date >= Carbon::now()) {
                    $active = true;
                }
            } else {
                if ($this->start_date) {
                    if ($this->start_date <= Carbon::now()) {
                        $active = true;
                    }
                } else {
                    if ($this->end_date >= Carbon::now()) {
                        $active = true;
                    }
                }
            }
        }
        if ($active) {
            if (! $this->sku || ! $this->price) {
                $active = false;
            }
        }
        if ($active) {
            if ($this->parent) {
                $active = $this->parent->public;
            }
        }

        return $active;
    }

    public function getCombinations($arrays)
    {
        $result = [[]];
        foreach ($arrays as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property => $property_value]);
                }
            }
            $result = $tmp;
        }

        return $result;
    }

    //Only used for old method/style
    public function filters()
    {
        $parentProduct = $this->parent;

        if ($parentProduct) {
            $childProducts = $parentProduct->childProducts()->publicShowable()->get();
            $activeFilters = $parentProduct->activeProductFiltersForVariations;
        } else {
            $childProducts = [
                $this,
            ];
            $activeFilters = $this->activeProductFiltersForVariations;
        }

        $showableFilters = [];
        $activeFiltersValues = [];

        foreach ($activeFilters as $activeFilter) {
            $filterOptionValues = [];
            foreach ($childProducts as $childProduct) {
                $filterName = '';
                $activeFilterId = '';
                $activeFilterOptionIds = [];
                $activeFilterOptions = [];

                foreach ($activeFilter->productFilterOptions as $option) {
                    if ($childProduct->productFilters()->where('product_filter_option_id', $option->id)->exists()) {
                        if ($filterName) {
                            $filterName .= ', ';
                            $activeFilterId .= '-';
                        }
                        $filterName .= $option->name;
                        $activeFilterId .= $option->id;
                        $activeFilterOptionIds[] = $option->id;
                        $activeFilterOptions[] = $option;
                    }
                }

                //If something does not work correct, check if below code makes sure there is a active one
                //Array key must be string, otherwise Livewire renders it in order of id, instead of order from filter option
                if (count($activeFilterOptionIds) && (! array_key_exists('filter-' . $activeFilterId, $filterOptionValues) || $this->id == $childProduct->id)) {
                    $filterOptionValues['filter-' . $activeFilterId] = [
                        'id' => $activeFilter->id,
                        'name' => $filterName,
                        'order' => $activeFilterOptions[0]->order,
                        'activeFilterOptionIds' => $activeFilterOptionIds,
                        'value' => implode('-', $activeFilterOptionIds),
                        'active' => $this->id == $childProduct->id,
                        'url' => ($this->id == $childProduct->id) ? $this->getUrl() : '',
                        'productId' => ($this->id == $childProduct->id) ? $this->id : '',
                        'in_stock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                        'inStock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                        'isPreOrder' => ($this->id == $childProduct->id) ? $this->isPreorderable() : false,
                    ];
                    if ($this->id == $childProduct->id) {
                        $activeFiltersValues['filter-' . $activeFilterId] = [
                            'id' => $activeFilter->id,
                            'name' => $filterName,
                            'activeFilterOptionIds' => $activeFilterOptionIds,
                            'value' => implode('-', $activeFilterOptionIds),
                            'active' => $this->id == $childProduct->id,
                            'url' => ($this->id == $childProduct->id) ? $this->getUrl() : '',
                            'productId' => ($this->id == $childProduct->id) ? $this->id : '',
                            'in_stock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                            'inStock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                            'isPreOrder' => ($this->id == $childProduct->id) ? ($this->isPreorderable()) : false,
                        ];

                        $activeFilterValue = implode('-', $activeFilterOptionIds);
                    }
                }
            }

            $showableFilters[] = [
                'id' => $activeFilter->id,
                'name' => $activeFilter->name,
                'active' => $activeFilterValue ?? null,
                'defaultActive' => $activeFilterValue ?? null,
                'values' => $filterOptionValues,
                'contentBlocks' => $activeFilter->contentBlocks,
            ];
        }

        foreach ($showableFilters as &$showableFilter) {
            $correctFilterOptions = 0;
            foreach ($showableFilter['values'] as &$showableFilterValue) {
                if (! $showableFilterValue['url']) {
                    foreach ($childProducts as $childProduct) {
                        if ($childProduct->id != $this->id) {
                            $productIsCorrectForFilter = true;
                            foreach ($showableFilterValue['activeFilterOptionIds'] as $activeFilterOptionId) {
                                if (! $childProduct->productFilters()->where('product_filter_option_id', $activeFilterOptionId)->exists()) {
                                    $productIsCorrectForFilter = false;
                                }
                            }
                            if ($productIsCorrectForFilter) {
                                foreach ($activeFiltersValues as $activeFilterValue) {
                                    if ($activeFilterValue['id'] != $showableFilterValue['id']) {
                                        $productHasCorrectFilterOption = true;
                                        foreach ($activeFilterValue['activeFilterOptionIds'] as $activeFilterOptionId) {
                                            if (! $childProduct->productFilters()->where('product_filter_option_id', $activeFilterOptionId)->exists()) {
                                                $productHasCorrectFilterOption = false;
                                            }
                                        }
                                        if (! $productHasCorrectFilterOption) {
                                            $productIsCorrectForFilter = false;
                                        }
                                    }
                                }
                            }
                            if ($productIsCorrectForFilter) {
                                $showableFilterValue['url'] = $childProduct->getUrl();
                                $showableFilterValue['productId'] = $childProduct->id;
                                $showableFilterValue['in_stock'] = $childProduct->inStock();
                                $showableFilterValue['inStock'] = $childProduct->inStock();
                                $showableFilterValue['isPreOrder'] = $childProduct->isPreorderable();
                                $correctFilterOptions++;
                            }
                        }
                    }
                } else {
                    $correctFilterOptions++;
                }
            }
            $showableFilter['correctFilterOptions'] = $correctFilterOptions;
        }

        foreach ($showableFilters as &$showableFilter) {
            $showableFilter['values'] = collect($showableFilter['values'])->sortBy('order');
        }

        return $showableFilters;
    }

    public function simpleFilters(): array
    {
        $filters = [];

        foreach ($this->activeProductFilters as $filter) {
            if ($filter->pivot->use_for_variations) {
                $filterOptions = $filter->productFilterOptions()->whereIn('id', $this->enabledProductFilterOptions()->pluck('product_filter_option_id'))->get()->toArray();

                if (count($filterOptions)) {
                    foreach ($filterOptions as &$filterOption) {
                        $filterOption['name'] = $filterOption['name'][App::getLocale()] ?? $filterOption['name'][0];
                    }

                    $filters[] = [
                        'id' => $filter->id,
                        'name' => $filter['name'],
                        'options' => $filterOptions,
                        'active' => null,
                    ];
                }
            }
        }

        return $filters;
    }

    public function possibleVariations(): array
    {
        $variations = [];

        $activeFilters = $this->activeProductFiltersForVariations;

        foreach ($activeFilters as $filter) {
            $variations[$filter->id] = $filter->productFilterOptions()->whereIn('id', $this->enabledProductFilterOptions()->pluck('product_filter_option_id'))->pluck('id');
        }

        return $this->getCombinations($variations);
    }

    public function missingVariations(): array
    {
        $variations = $this->possibleVariations();

        foreach ($variations as $variationKey => $variation) {
            if ($this->variationExists($variation)) {
                unset($variations[$variationKey]);
            }
        }

        return $variations;
    }

    public function variationExists(array $array): bool
    {
        foreach ($this->childProducts as $childProduct) {
            $arrayToCheck = &$array;
            foreach ($childProduct->productFilters as $filter) {
                $key = array_search($filter->pivot->product_filter_option_id, $arrayToCheck);
                if ($key !== false) {
                    unset($arrayToCheck[$key]);
                }
            }
            if (count($arrayToCheck) == 0) {
                return true;
            }
        }

        return false;
    }

    public function reservedStock()
    {
        return OrderProduct::where('product_id', $this->id)->whereIn('order_id', Order::whereIn('status', ['pending'])->pluck('id'))->count();
    }

    public function stock()
    {
        return $this->total_stock;
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
                $stock = $this->stock - $this->reservedStock();
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

    public function hasDirectSellableStock()
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
        } elseif ($this->childProducts()->count()) {
            foreach ($this->childProducts as $childProduct) {
                if ($childProduct->hasDirectSellableStock()) {
                    return true;
                }
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
        } elseif ($this->childProducts()->count()) {
            foreach ($this->childProducts as $childProduct) {
                if ($childProduct->inStock()) {
                    $inStock = true;
                }
            }
        } else {
            if ($this->type == 'simple') {
                $inStock = $this->stock() > 0;
            } elseif ($this->type == 'variable') {
                if ($this->parent) {
                    $inStock = $this->stock() > 0;
                } else {
                    foreach ($this->childProducts() as $childProduct) {
                        if ($childProduct->inStock()) {
                            $inStock = true;
                        }
                    }
                }
            }
        }

        $this->in_stock = $inStock;
        $this->saveQuietly();
    }

    public function calculateTotalPurchases(): void
    {
        $purchases = $this->purchases;

        foreach ($this->childProducts as $childProduct) {
            $purchases = $purchases + $childProduct->purchases;
        }

        $this->total_purchases = $purchases;
        $this->saveQuietly();
    }

    public function outOfStockSellable()
    {
        //Todo: make editable if expectedInStockDateValid should be checked or not

        if (! $this->use_stock) {
            if ($this->stock_status == 'out_of_stock') {
                return false;
            }
        }

        if (! $this->out_of_stock_sellable) {
            return false;
        }

        if (Customsetting::get('product_out_of_stock_sellable_date_should_be_valid', Sites::getActive(), 1) && ! $this->expectedInStockDateValid()) {
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

    public function expectedInStockDateInWeeks()
    {
        $expectedInStockDate = self::expectedInStockDate();
        if (! $expectedInStockDate || Carbon::parse($expectedInStockDate) < now()) {
            return 0;
        }

        $diffInWeeks = Carbon::parse($expectedInStockDate)->diffInWeeks(now()->subDay());
        if ($diffInWeeks < 0) {
            $diffInWeeks = 0;
        }

        return $diffInWeeks;
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
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function childProducts()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function productCategories()
    {
        return $this->belongsToMany(ProductCategory::class, 'dashed__product_category');
    }

    public function suggestedProducts()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_suggested_product', 'product_id', 'suggested_product_id');
    }

    public function shippingClasses()
    {
        return $this->belongsToMany(ShippingClass::class, 'dashed__product_shipping_class');
    }

    public function productFilters()
    {
        return $this->belongsToMany(ProductFilter::class, 'dashed__product_filter')->orderBy('created_at')->withPivot(['product_filter_option_id']);
    }

    public function enabledProductFilterOptions()
    {
        return $this->belongsToMany(ProductFilter::class, 'dashed__product_enabled_filter_options')->withPivot(['product_filter_option_id']);
    }

    public function activeProductFilters()
    {
        return $this->belongsToMany(ProductFilter::class, 'dashed__active_product_filter')->orderBy('order')->withPivot(['use_for_variations']);
    }

    public function activeProductFiltersForVariations()
    {
        return $this->belongsToMany(ProductFilter::class, 'dashed__active_product_filter')->orderBy('created_at')->wherePivot('use_for_variations', 1)->withPivot(['use_for_variations']);
    }

    public function productCharacteristics()
    {
        return $this->hasMany(ProductCharacteristic::class);
    }

    public function productExtras()
    {
        return $this->hasMany(ProductExtra::class)->with(['ProductExtraOptions']);
    }

    public function allProductExtras()
    {
        return ProductExtra::where('product_id', $this->id)->orWhere('product_id', $this->parent_id)->with(['ProductExtraOptions'])->get();
    }

    public function bundleProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'dashed__product_bundle_products', 'product_id', 'bundle_product_id');
    }

    public function showableCharacteristics($withoutIds = [])
    {
        return Cache::tags(["product-$this->id"])->rememberForever("product-showable-characteristics-" . $this->id, function () use ($withoutIds) {
            $characteristics = [];

            $parentProduct = $this->parent;

            if ($parentProduct) {
                $activeFilters = $parentProduct->activeProductFilters;
            } else {
                $activeFilters = $this->activeProductFilters;
            }

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

            return $characteristics;
        });
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

    public function getSuggestedProducts($limit = 4)
    {
        $suggestedProductIds = $this->suggestedProducts->pluck('id')->toArray();

        if (count($suggestedProductIds) < $limit) {
            $randomProductIds = Product::thisSite()->publicShowable()
                ->where('id', '!=', $this->id)
                ->whereNotIn('id', $suggestedProductIds)
                ->limit($limit - count($suggestedProductIds))
                ->inRandomOrder()
                ->pluck('id')->toArray();
            $suggestedProductIds = array_merge($randomProductIds, $suggestedProductIds);
        }

        return Product::thisSite()->publicShowable()->whereIn('id', $suggestedProductIds)->limit($limit)->inRandomOrder()->get();
    }

    public function getShoppingCartItemPrice(CartItem $cartItem, string|DiscountCode|null $discountCode = null)
    {
        if ($discountCode && is_string($discountCode)) {
            $discountCode = null;
        }

        $quantity = $cartItem->qty;
        $options = $cartItem->options;

        $price = 0;

        $price += $this->currentPrice * $quantity;

        foreach ($options as $productExtraOptionId => $productExtraOption) {
            if (! str($productExtraOptionId)->contains('product-extra-')) {
                $thisProductExtraOption = ProductExtraOption::find($productExtraOptionId);
                if ($thisProductExtraOption) {
                    if ($thisProductExtraOption->calculate_only_1_quantity) {
                        $price += $thisProductExtraOption->price;
                    } else {
                        $price += ($thisProductExtraOption->price * $quantity);
                    }
                }
            }
        }

        if ($discountCode && $discountCode->type == 'percentage') {
            $discountValidForProduct = false;

            if ($discountCode->valid_for == 'categories') {
                if ($discountCode->productCategories()->whereIn('product_category_id', $cartItem->model->productCategories()->pluck('product_category_id'))->exists()) {
                    $discountValidForProduct = true;
                }
            } elseif ($discountCode->valid_for == 'products') {
                if ($discountCode->products()->where('product_id', $cartItem->model->id)->exists()) {
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

    public static function resolveRoute($parameters = [])
    {
        $slug = $parameters['slug'] ?? '';
        $slugComponents = explode('/', $slug);

        if ($slugComponents[0] == Translation::get('products-slug', 'slug', 'products') && count($slugComponents) == 2) {
            $product = Product::thisSite()->where('slug->' . App::getLocale(), $slugComponents[1]);
            if (! auth()->check() || auth()->user()->role != 'admin') {
                $product->publicShowable(true);
            }
            $product = $product->first();

            if (! $product) {
                foreach (Product::thisSite()->publicShowable(true)->get() as $possibleProduct) {
                    if (! $product && $possibleProduct->slug == $slugComponents[1]) {
                        $product = $possibleProduct;
                    }
                }
            }

            if ($product) {
                if (View::exists('dashed.products.show')) {
                    seo()->metaData('metaTitle', $product->metadata && $product->metadata->title ? $product->metadata->title : $product->name);
                    seo()->metaData('metaDescription', $product->metadata->description ?? '');
                    $metaImage = $product->metadata->image ?? '';
                    if (! $metaImage) {
                        $metaImage = $product->firstImageUrl;
                    }
                    if ($metaImage) {
                        seo()->metaData('metaImage', $metaImage);
                    }

                    View::share('product', $product);

                    return view('dashed.products.show');
                } else {
                    return 'pageNotFound';
                }
            }
        }
    }
}
