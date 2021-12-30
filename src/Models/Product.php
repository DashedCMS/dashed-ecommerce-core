<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Qubiqx\QcommerceCore\Classes\Sites;
use Spatie\Translatable\HasTranslations;
use Qubiqx\QcommerceCore\Classes\Locales;
use Spatie\Activitylog\Traits\LogsActivity;
use Qubiqx\QcommerceCore\Models\Translation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class Product extends Model
{
    use HasTranslations;
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'qcommerce__products';

    protected $fillable = [
        'site_ids',
        'name',
        'slug',
        'short_description',
        'description',
        'search_terms',
        'weight',
        'length',
        'width',
        'height',
        'price',
        'new_price',
        'sku',
        'ean',
        'public',
        'type',
        'start_date',
        'end_date',
        'external_url',
        'use_stock',
        'stock_status',
        'stock',
        'images',
        'out_of_stock_sellable',
        'expected_in_stock_date',
        'low_stock_notification',
        'low_stock_notification_limit',
        'limit_purchases_per_customer',
        'limit_purchases_per_customer_limit',
        'purchases',
        'content',
        'meta_title',
        'meta_description',
        'meta_image',
        'parent_product_id',
        'order',
        'efulfillment_shop_id',
        'efulfillment_shop_error',
        'only_show_parent_product',
        'vat_rate',
    ];

    public $translatable = [
        'name',
        'slug',
        'short_description',
        'description',
        'search_terms',
        'content',
        'images',
        'meta_title',
        'meta_description',
        'meta_image',
    ];

    protected $dates = [
        'start_date',
        'end_date',
        'expected_in_stock_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $with = [
        'productFilters',
        'parentProduct',
    ];

    protected $casts = [
        'site_ids' => 'array',
        'images' => 'array',
    ];

    protected static function booted()
    {
        static::created(function ($product) {
            Cache::tags(['products', 'product-' . $product->id])->flush();
            if ($product->parentProduct) {
                Cache::tags(['product-' . $product->parentProduct->id])->flush();
                foreach ($product->parentProduct->childProducts as $childProduct) {
                    Cache::tags(['product-' . $childProduct->id])->flush();
                }
            }
        });

        static::updated(function ($product) {
            Cache::tags(['products', 'product-' . $product->id])->flush();
            if ($product->parentProduct) {
                Cache::tags(['product-' . $product->parentProduct->id])->flush();
                foreach ($product->parentProduct->childProducts as $childProduct) {
                    Cache::tags(['product-' . $childProduct->id])->flush();
                }
            }
        });
    }

    public function scopeSearch($query)
    {
        $minPrice = request()->get('min-price') ? request()->get('min-price') : null;
        $maxPrice = request()->get('max-price') ? request()->get('max-price') : null;

        $search = request()->get('search');

        if ($minPrice) {
            $query->where('price', '>=', $minPrice);
        }
        if ($maxPrice) {
            $query->where('price', '<=', $maxPrice);
        }

        $query->where(function ($query) use ($search) {
            $query
                ->whereRaw('LOWER(name) like ?', '%' . strtolower($search) . '%')
                ->orWhereRaw('LOWER(content) like ?', '%' . strtolower($search) . '%')
                ->orWhereRaw('LOWER(short_description) like ?', '%' . strtolower($search) . '%')
                ->orWhereRaw('LOWER(description) like ?', '%' . strtolower($search) . '%')
                ->orWhereRaw('LOWER(search_terms) like ?', '%' . strtolower($search) . '%')
                ->orWhere('slug', 'LIKE', "%$search%")
                ->orWhere('weight', 'LIKE', "%$search%")
                ->orWhere('length', 'LIKE', "%$search%")
                ->orWhere('width', 'LIKE', "%$search%")
                ->orWhere('height', 'LIKE', "%$search%")
                ->orWhere('price', 'LIKE', "%$search%")
                ->orWhere('new_price', 'LIKE', "%$search%")
                ->orWhere('sku', 'LIKE', "%$search%")
                ->orWhere('ean', 'LIKE', "%$search%")
                ->orWhere('meta_title', 'LIKE', "%$search%")
                ->orWhere('meta_description', 'LIKE', "%$search%");
        });
    }

    public function scopeThisSite($query)
    {
        $query->where('site_ids->' . Sites::getActive(), 'active');
    }

    public function scopePublic($query)
    {
        $query->where('public', 1);
    }

    public function scopeNotParentProduct($query)
    {
        $query->where(function ($query) {
            $query->where('type', '!=', 'variable');
        })->orWhere(function ($query) {
            $query->where('type', 'variable')
                ->where('parent_product_id', '!=', null);
        });
    }

    public function scopePublicShowable($query)
    {
        $query
            ->public()
            ->thisSite()
            ->where('sku', '!=', null)
            ->where('price', '!=', null)
            ->notParentProduct()
            ->where(function ($query) {
                $query->where('start_date', null);
            })->orWhere(function ($query) {
                $query->where('start_date', '<=', Carbon::now());
            })->where(function ($query) {
                $query->where('end_date', null);
            })->orWhere(function ($query) {
                $query->where('end_date', '>=', Carbon::now());
            });
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
                    ->where('parent_product_id', '!=', null)
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
        $query->where('parent_product_id', null);
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
        if ($productCategory) {
            while ($productCategory) {
                $breadcrumbs[] = [
                    'name' => $productCategory->name,
                    'url' => $productCategory->getUrl(),
                ];
                $productCategory = ProductCategory::find($productCategory->parent_category_id);
            }
        }

        return array_reverse($breadcrumbs);
    }

    public function getTotalPurchasesAttribute()
    {
        $purchases = $this->purchases;

        foreach ($this->childProducts as $childProduct) {
            $purchases = $purchases + $childProduct->purchases;
        }

        return $purchases;
    }

    public function getCurrentPriceAttribute()
    {
        if ($this->childProducts()->count()) {
            return $this->childProducts()->first()->price;
        } else {
            return $this->price;
        }
    }

    public function getDiscountPriceAttribute()
    {
        if ($this->new_price) {
            return $this->new_price;
        } else {
            return null;
        }
    }

    public function getFirstImageUrlAttribute()
    {
        return collect($this->images)->sortBy('order')->first()['image'] ?? '';
    }

    //    public function getImagesAttribute()
//    {
//        return Cache::tags(["product-$this->id"])->rememberForever("product-images-attribute-" . $this->id, function () {
//            if ($this->childProducts()->count()) {
//                $images = $this->childProducts()->first()->getMedia('images-' . app()->getLocale());
//            } else {
//                $images = $this->getMedia('images-' . app()->getLocale());
//            }
//            if ($images) {
//                return $images;
//            } else {
//                return [];
//            }
//        });
//    }

    public function getUrl($locale = null)
    {
        if (! $locale) {
            $locale = App::getLocale();
        }

        if ($this->childProducts()->count()) {
            $url = $this->childProducts()->first()->getUrl();
        } else {
            $url = '/' . Translation::get('products-slug', 'slug', 'products') . '/' . $this->slug;
        }

        if ($locale != config('app.locale')) {
            $url = App::getLocale() . '/' . $url;
        }

        return LaravelLocalization::localizeUrl($url);
    }

    public function activeSiteIds()
    {
        $sites = [];
        foreach (Sites::getSites() as $site) {
            if (self::where('id', $this->id)->where('site_ids->' . $site['id'], 'active')->count()) {
                array_push($sites, $site['id']);
            }
        }

        return $sites;
    }

    public function activeLocaleIds()
    {
        $sites = [];
        foreach (Locales::getLocales() as $site) {
            if (self::where('id', $this->id)->where('site_ids->' . $site['id'], 'active')->count()) {
                array_push($sites, $site['id']);
            }
        }

        return $sites;
    }

    public function siteNames()
    {
        $sites = [];
        foreach (Sites::getSites() as $site) {
            if (DiscountCode::where('id', $this->id)->where('site_ids->' . $site['id'], 'active')->count()) {
                $sites[$site['name']] = 'active';
            } else {
                $sites[$site['name']] = 'inactive';
            }
        }

        return $sites;
    }

    public function getStatusAttribute()
    {
        if (! $this->public) {
            return false;
        }

        if ($this->type == 'variable') {
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
            if ($this->parentProduct) {
                $active = $this->parentProduct->public;
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

    public function filters()
    {
        $parentProduct = $this->parentProduct;

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

                foreach ($activeFilter->productFilterOptions as $option) {
                    if ($childProduct->productFilters()->where('product_filter_option_id', $option->id)->exists()) {
                        if ($filterName) {
                            $filterName .= ', ';
                            $activeFilterId .= '-';
                        }
                        $filterName .= $option->name;
                        $activeFilterId .= $option->id;
                        $activeFilterOptionIds[] = $option->id;
                    }
                }

                //If something does not work correct, check if below code makes sure there is a active one
                if (count($activeFilterOptionIds) && (! array_key_exists($activeFilterId, $filterOptionValues) || $this->id == $childProduct->id)) {
                    $filterOptionValues[$activeFilterId] = [
                        'id' => $activeFilter->id,
                        'name' => $filterName,
                        'activeFilterOptionIds' => $activeFilterOptionIds,
                        'active' => $this->id == $childProduct->id,
                        'url' => ($this->id == $childProduct->id) ? $this->getUrl() : '',
                        'productId' => ($this->id == $childProduct->id) ? $this->id : '',
                        'in_stock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                        'inStock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                        'directSellableStock' => ($this->id == $childProduct->id) ? $this->hasDirectSellableStock() : false,
                    ];
                    if ($this->id == $childProduct->id) {
                        $activeFiltersValues[$activeFilterId] = [
                            'id' => $activeFilter->id,
                            'name' => $filterName,
                            'activeFilterOptionIds' => $activeFilterOptionIds,
                            'active' => $this->id == $childProduct->id,
                            'url' => ($this->id == $childProduct->id) ? $this->getUrl() : '',
                            'productId' => ($this->id == $childProduct->id) ? $this->id : '',
                            'in_stock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                            'inStock' => ($this->id == $childProduct->id) ? $this->inStock() : false,
                            'isPreOrder' => ($this->id == $childProduct->id) ? ($this->isPreorderable()) : false,
                        ];
                    }
                }
            }

            $showableFilters[] = [
                'id' => $activeFilter->id,
                'name' => $activeFilter->name,
                'values' => $filterOptionValues,
            ];
        }

        foreach ($showableFilters as &$showableFilter) {
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
                            }
                        }
                    }
                }
            }
        }

        return $showableFilters;
    }

    public function reservedStock()
    {
        return OrderProduct::where('product_id', $this->id)->whereIn('order_id', Order::whereIn('status', ['pending'])->pluck('id'))->count();
    }

    public function stock()
    {
        if ($this->use_stock) {
            if ($this->outOfStockSellable()) {
                return 100000;
            } else {
                return $this->stock - $this->reservedStock();
            }
        } else {
            if ($this->stock_status == 'in_stock') {
                return 100000;
            } else {
                return 0;
            }
        }
    }

    public function hasDirectSellableStock()
    {
        if ($this->childProducts()->count()) {
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

    public function inStock()
    {
        if ($this->childProducts()->count()) {
            foreach ($this->childProducts as $childProduct) {
                if ($childProduct->inStock()) {
                    return true;
                }
            }
        } else {
            if ($this->type == 'simple') {
                return $this->stock() > 0;
            } elseif ($this->type == 'variable') {
                if ($this->parentProduct) {
                    return $this->stock() > 0;
                } else {
                    foreach ($this->childProducts() as $childProduct) {
                        if ($childProduct->inStock()) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
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

    public function parentProduct()
    {
        return $this->belongsTo(self::class, 'parent_product_id');
    }

    public function childProducts()
    {
        return $this->hasMany(self::class, 'parent_product_id');
    }

    public function productCategories()
    {
        return $this->belongsToMany(ProductCategory::class, 'qcommerce__product_category');
    }

    public function suggestedProducts()
    {
        return $this->belongsToMany(Product::class, 'qcommerce__product_suggested_product', 'product_id', 'suggested_product_id');
    }

    public function shippingClasses()
    {
        return $this->belongsToMany(ShippingClass::class, 'qcommerce__product_shipping_class');
    }

    public function productFilters()
    {
//        return Cache::tags(["product-$this->id"])->rememberForever("product-filters-" . $this->id, function () {
//            return $this->productFiltersRelation();
//        });
        return $this->belongsToMany(ProductFilter::class, 'qcommerce__product_filter')->orderBy('created_at')->withPivot(['product_filter_option_id']);
    }

    public function activeProductFilters()
    {
        return $this->belongsToMany(ProductFilter::class, 'qcommerce__active_product_filter')->orderBy('created_at')->withPivot(['use_for_variations']);
    }

    public function activeProductFiltersForVariations()
    {
        return $this->belongsToMany(ProductFilter::class, 'qcommerce__active_product_filter')->orderBy('created_at')->wherePivot('use_for_variations', 1)->withPivot(['use_for_variations']);
    }

    public function productCharacteristics()
    {
        return $this->hasMany(ProductCharacteristic::class);
    }

//    public function montaPortalProduct()
//    {
//        return $this->hasOne(MontaportalProduct::class);
//    }

//    public function exactonlineProduct()
//    {
//        return $this->hasOne(ExactonlineProduct::class);
//    }

    public function productExtras()
    {
        return $this->hasMany(ProductExtra::class);
    }

    public function allProductExtras()
    {
        return ProductExtra::where('product_id', $this->id)->orWhere('product_id', $this->parent_product_id)->get();
    }

    public function showableCharacteristics($withoutIds = [])
    {
        $characteristics = [];

        $parentProduct = $this->parentProduct;

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

        foreach ($this->productCharacteristics as $productCharacteristic) {
            if ($productCharacteristic->value && ! $productCharacteristic->productCharacteristic->hide_from_public && ! in_array($productCharacteristic->product_characteristic_id, $withoutIds)) {
                $characteristics[] = [
                    'name' => $productCharacteristic->productCharacteristic->name,
                    'value' => $productCharacteristic->value,
                ];
            }
        }

        return $characteristics;
    }

    public function getCharacteristicById($id)
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

        return;
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
}
