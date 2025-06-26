<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedPages\Models\Page;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

class ProductGroup extends Model
{
    use SoftDeletes;
    use HasDynamicRelation;
    use IsVisitable;

    protected $table = 'dashed__product_groups';

    public $translatable = [
        'name',
        'slug',
        'short_description',
        'description',
        'content',
        'images',
        'search_terms',
    ];

    protected $casts = [
        'site_ids' => 'array',
        'images' => 'array',
        'missing_variations' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(function ($productGroup) {
            $productGroup->removeInvalidImages();
            UpdateProductInformationJob::dispatch($productGroup)->onQueue('ecommerce');
        });

        static::deleting(function ($productGroup) {
            foreach ($productGroup->products as $product) {
                $product->delete();
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function firstSelectedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'first_selected_product_id');
    }

    public function activeProductFilters()
    {
        return $this->belongsToMany(ProductFilter::class, 'dashed__active_product_filter')
            ->orderBy('order')
            ->withPivot(['use_for_variations']);
    }

    public function activeProductFiltersForVariations()
    {
        return $this->belongsToMany(ProductFilter::class, 'dashed__active_product_filter')
            ->orderBy('created_at')
            ->wherePivot('use_for_variations', 1)
            ->withPivot(['use_for_variations']);
    }

    public function enabledProductFilterOptions()
    {
        return $this->belongsToMany(ProductFilter::class, 'dashed__product_enabled_filter_options')
            ->withPivot(['product_filter_option_id']);
    }

    public function productCharacteristics()
    {
        return $this->hasMany(ProductCharacteristic::class);
    }

    public function productCategories()
    {
        return $this->belongsToMany(ProductCategory::class, 'dashed__product_category');
    }

    public function suggestedProducts()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_suggested_product', 'product_group_id', 'suggested_product_id');
    }

    public function crossSellProducts()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_crosssell_product', 'product_group_id', 'crosssell_product_id');
    }

    public function tabs()
    {
        return $this->belongsToMany(ProductTab::class, 'dashed__product_tab_product', 'product_group_id', 'tab_id')
            ->orderBy('order');
    }

    public function globalTabs()
    {
        return $this->belongsToMany(ProductTab::class, 'dashed__product_tab_product', 'product_group_id', 'tab_id')
            ->orderBy('order')
            ->where('global', 1);
    }

    public function ownTabs()
    {
        return $this->belongsToMany(ProductTab::class, 'dashed__product_tab_product', 'product_group_id', 'tab_id')
            ->orderBy('order')
            ->where('global', 0);
    }

    //    public function allProductExtras(): ?Collection
    //    {
    //        $productExtraIds = [];
    //
    //        $productExtraIds = array_merge($productExtraIds, $this->productExtras->pluck('id')->toArray());
    //        $productExtraIds = array_merge($productExtraIds, $this->globalProductExtras->pluck('id')->toArray());
    //
    //        if ($this->parent) {
    //            $productExtraIds = array_merge($productExtraIds, $this->parent->productExtras->pluck('id')->toArray());
    //            $productExtraIds = array_merge($productExtraIds, $this->parent->globalProductExtras->pluck('id')->toArray());
    //        }
    //
    //        return ProductExtra::whereIn('id', $productExtraIds)
    //            ->with(['ProductExtraOptions'])
    //            ->get();
    //    }

    public function productExtras(): HasMany
    {
        return $this->hasMany(ProductExtra::class)
            ->orderBy('order')
            ->with(['productExtraOptions']);
    }

    public function globalProductExtras(): BelongsToMany
    {
        return $this->belongsToMany(ProductExtra::class, 'dashed__product_extra_product', 'product_group_id', 'product_extra_id')
            ->orderBy('order')
            ->where('global', 1)
            ->with(['productExtraOptions']);
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
        foreach ($this->products as $product) {
            $arrayToCheck = &$array;
            foreach ($product->productFilters as $filter) {
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

    public function possibleVariations(): array
    {
        $variations = [];

        $activeFilters = $this->activeProductFiltersForVariations;

        foreach ($activeFilters as $filter) {
            $variations[$filter->id] = $filter->productFilterOptions()->whereIn('id', $this->enabledProductFilterOptions()->pluck('product_filter_option_id'))->pluck('id');
        }

        return $this->getCombinations($variations);
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

    public function simpleFilters(): array
    {
        $filters = [];

        foreach ($this->activeProductFilters as $filter) {
            if ($filter->pivot->use_for_variations) {
                $filterOptions = $filter->productFilterOptions()->whereIn('id', $this->enabledProductFilterOptions()->pluck('product_filter_option_id'))->get()->toArray();

                if (count($filterOptions)) {
                    foreach ($filterOptions as &$filterOption) {
                        $filterOption['name'] = $filterOption['name'][app()->getLocale()] ?? ($filterOption['name'][array_key_first($filterOption['name'])] ?? 'onbekend');
                    }

                    $filters[] = [
                        'id' => $filter->id,
                        'name' => $filter['name'],
                        'options' => $filterOptions,
                        'type' => $filter->type,
                        'active' => null,
                        'contentBlocks' => $filter->contentBlocks,
                    ];
                }
            }
        }

        return $filters;
    }

    public function showableCharacteristics($withoutIds = [])
    {
        return Cache::rememberForever("product-group-showable-characteristics-" . $this->id, function () use ($withoutIds) {
            $characteristics = [];

            $activeFilters = $this->activeProductFilters;

            foreach ($activeFilters as $activeFilter) {
                $value = '';
                foreach ($activeFilter->productFilterOptions as $option) {
                    if ($this->enabledProductFilterOptions()->where('product_filter_option_id', $option->id)->exists()) {
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
                if ($thisProductCharacteristic && $thisProductCharacteristic->value && !$productCharacteristic->hide_from_public && !in_array($productCharacteristic->id, $withoutIds)) {
                    $characteristics[] = [
                        'name' => $productCharacteristic->name,
                        'value' => $thisProductCharacteristic->value,
                    ];
                }
            }

            return $characteristics;
        });
    }

    public function allCharacteristicsWithoutFilters($withoutIds = [])
    {
        $characteristics = [];

        $allProductCharacteristics = ProductCharacteristics::orderBy('order')->get();
        foreach ($allProductCharacteristics as $productCharacteristic) {
            $thisProductCharacteristic = $this->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->first();
            if ($thisProductCharacteristic && $thisProductCharacteristic->value && !in_array($productCharacteristic->id, $withoutIds)) {
                $characteristics[] = [
                    'name' => $productCharacteristic->name,
                    'value' => $thisProductCharacteristic->value,
                ];
            }
        }

        return $characteristics;
    }

    public function showableCharacteristicsWithoutFilters($withoutIds = [])
    {
        return Cache::rememberForever("product-group-showable-characteristics-without-filters-" . $this->id, function () use ($withoutIds) {
            $characteristics = [];

            $allProductCharacteristics = ProductCharacteristics::orderBy('order')->get();
            foreach ($allProductCharacteristics as $productCharacteristic) {
                $thisProductCharacteristic = $this->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->first();
                if ($thisProductCharacteristic && $thisProductCharacteristic->value && !$productCharacteristic->hide_from_public && !in_array($productCharacteristic->id, $withoutIds)) {
                    $characteristics[] = [
                        'name' => $productCharacteristic->name,
                        'value' => $thisProductCharacteristic->value,
                    ];
                }
            }

            return $characteristics;
        });
    }

    public function allProductTabs(): ?Collection
    {
        $productTabIds = [];

        $productTabIds = array_merge($productTabIds, $this->tabs->pluck('id')->toArray());
        $productTabIds = array_merge($productTabIds, $this->globalTabs->pluck('id')->toArray());

        return ProductTab::whereIn('id', $productTabIds)
            ->get();
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

    public function getUrl($activeLocale = null, bool $native = true)
    {
        $originalLocale = app()->getLocale();

        if (!$activeLocale) {
            $activeLocale = $originalLocale;
        }

        $overviewPage = Product::getOverviewPage();
        if ($overviewPage) {
            $url = "{$overviewPage->getUrl($activeLocale)}/{$this->getTranslation('slug', $activeLocale)}";
        } else {
            $url = $this->getTranslation('slug', $activeLocale);
        }

        if (!str($url)->startsWith('/')) {
            $url = '/' . $url;
        }
        if ($activeLocale != Locales::getFirstLocale()['id'] && !str($url)->startsWith("/{$activeLocale}")) {
            $url = '/' . $activeLocale . $url;
        }

        return $native ? $url : url($url);
    }

    public static function getOverviewPage(): ?Page
    {
        return Product::getOverviewPage();
    }

    public static function canHaveParent(): bool
    {
        return false;
    }

    public static function returnForRoute(): array
    {
        return [
            'livewireComponent' => ShowProduct::class,
        ];
    }

    public function volumeDiscounts(): HasMany
    {
        return $this->hasMany(ProductGroupVolumeDiscount::class);
    }

    public function inStock(): bool
    {
        foreach ($this->products as $product) {
            if ($product->inStock()) {
                return true;
            }
        }

        return false;
    }

    public function stock()
    {
        $stock = 0;

        foreach ($this->products as $product) {
            $stock += $product->total_stock;
            $stock -= $product->reservedStock();
        }

        return $stock;
    }

    public function hasDirectSellableStock(): bool
    {
        foreach ($this->products as $product) {
            if ($product->hasDirectSellableStock()) {
                return true;
            }
        }

        return false;
    }

    public function getFirstImageAttribute(): ?string
    {
        if (is_array($this->images) && count($this->images)) {
            return $this->images[0];
        }

        foreach ($this->products as $product) {
            if ($product->firstImage) {
                return $product->firstImage;
            }
        }

        return null;
    }

    public function fromPrice(): string
    {
        $lowestPrice = $this->products->min('price');

        return Translation::get('product-price-from', 'product', 'Vanaf :price:', 'text', [
            'price' => $lowestPrice ? CurrencyHelper::formatPrice($lowestPrice) : '€0,00',
        ]);
    }

    public function betweenPrice(): string
    {
        $lowestPrice = $this->products->min('price');
        $highestPrice = $this->products->max('price');

        if ($lowestPrice === $highestPrice) {
            return Translation::get('product-price-static', 'product', ':price:', 'text', [
                'price' => $lowestPrice ? CurrencyHelper::formatPrice($lowestPrice) : '€0,00',
            ]);
        }

        return Translation::get('product-price-between', 'product', ':lowestPrice: tot :highestPrice:', 'text', [
            'lowestPrice' => $lowestPrice ? CurrencyHelper::formatPrice($lowestPrice) : '€0,00',
            'highestPrice' => $highestPrice ? CurrencyHelper::formatPrice($highestPrice) : '€0,00',
        ]);
    }

    public function showSingleProduct(): bool
    {
        return $this->only_show_parent_product || $this->products->count() <= 1;
    }

    public function removeInvalidImages(): void
    {
        foreach (Locales::getActivatedLocalesFromSites() as $locale) {
            $images = $this->getTranslation('images', $locale);
            if (is_array($images)) {
                foreach ($images as $key => $image) {
                    if (!mediaHelper()->getSingleMedia($image, 'original')) {
                        unset($images[$key]);
                    }
                }
                $images = array_values($images);
                $this->setTranslation('images', $locale, $images);
            }
        }
        $this->saveQuietly();
    }
}
