<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Models\Concerns\IsVisitable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Dashed\DashedEcommerceCore\Classes\ProductCategories;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductCategoriesInformationJob;

class ProductCategory extends Model
{
    use SoftDeletes;
    use IsVisitable;
    use HasCustomBlocks;

    protected static $logFillable = true;

    protected $table = 'dashed__product_categories';

    public $translatable = [
        'name',
        'slug',
        'content',
        'image',
    ];

    protected $with = [
    ];

    protected $casts = [
        'site_ids' => 'array',
        'content' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($productCategory) {
        });

        static::updated(function ($productCategory) {
        });

        static::saved(function ($productCategory) {
            UpdateProductCategoriesInformationJob::dispatch()->onQueue('ecommerce');
        });

        static::deleting(function ($productCategory) {
            foreach ($productCategory->getChilds() as $child) {
                $child->delete();
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class);
    }

    public function getProductsUrl($locale = null)
    {
        if (! $locale) {
            $locale = app()->getLocale();
        }

        $url = $this->getTranslation('slug', $locale);
        $parentCategory = $this->parent;
        while ($parentCategory) {
            $url = $parentCategory->getTranslation('slug', $locale) . '/' . $url;
            $parentCategory = $parentCategory->parent;
        }

        return url($url);
    }

    public function getUrl($locale = null)
    {
        $originalLocale = app()->getLocale();

        if (! $locale) {
            $locale = app()->getLocale();
        }

        return Cache::rememberForever('product-category-url-' . $this->id . '-' . $locale, function () use ($locale, $originalLocale) {
            if (! $this->childs->count()) {
                if ($this->products->count() == 1) {
                    return $this->products->first()->getUrl($locale);
                } else {
                    return $this->getProductsUrl($locale);
                }
            }

            $url = $this->getTranslation('slug', $locale);
            $parentCategory = $this->parent;
            while ($parentCategory) {
                $url = $parentCategory->getTranslation('slug', $locale) . '/' . $url;
                $parentCategory = $parentCategory->parent;
            }

            app()->setLocale($locale);
            $url = Translation::get('categories-slug', 'slug', 'categories') . '/' . $url;
            app()->setLocale($originalLocale);

            if (! str($url)->startsWith('/')) {
                $url = '/' . $url;
            }

            if ($locale != Locales::getFirstLocale()['id'] && ! str($url)->startsWith("/{$locale}")) {
                $url = '/' . $locale . $url;
            }

            return $url;
        });
    }

    public function hasChilds()
    {
        return (bool)$this->getFirstChilds()->count();
    }

    public function getChilds(): array
    {
        return Cache::rememberForever('product-category-childs-' . $this->id, function () {
            $childs = [];
            $childProductCategories = self::where('parent_id', $this->id)->get();
            while ($childProductCategories->count()) {
                $childProductCategoryIds = [];
                foreach ($childProductCategories as $childProductCategory) {
                    $childProductCategoryIds[] = $childProductCategory->id;
                    $childs[] = $childProductCategory;
                }
                $childProductCategories = self::with(['products'])->whereIn('parent_id', $childProductCategoryIds)->get();
            }

            return $childs;
        });
    }

    public function getFirstChilds()
    {
        return Cache::rememberForever('product-category-first-childs-' . $this->id, function () {
            return self::where('parent_id', $this->id)
                ->get();
        });
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_category');
    }

    public function childs()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public static function resolveRoute($parameters = [])
    {
        $slug = $parameters['slug'] ?? '';
        $slugComponents = explode('/', $slug);

        $productCategory = ProductCategory::thisSite()->slug($slugComponents[0])->first();

        if ($productCategory) {
            array_shift($slugComponents);
            foreach ($slugComponents as $slugComponent) {
                if (! $productCategory) {
                    return 'pageNotFound';
                }
                $productCategory = ProductCategory::thisSite()->slug($slugComponent)->where('parent_id', $productCategory->id)->first();
            }
            if (View::exists(config('dashed-core.site_theme') . '.categories.show') && $productCategory) {
                seo()->metaData('metaTitle', $productCategory->metadata && $productCategory->metadata->title ? $productCategory->metadata->title : $productCategory->name);
                seo()->metaData('metaDescription', $productCategory->metadata->description ?? '');
                if ($productCategory->metadata && $productCategory->metadata->image) {
                    seo()->metaData('metaImage', $productCategory->metadata->image);
                }

                View::share('model', $productCategory);
                View::share('productCategory', $productCategory);

                return view(config('dashed-core.site_theme') . '.categories.show');
            } else {
                return 'pageNotFound';
            }
        }

        if (Customsetting::get('product_category_index_page_enabled', null, true) && $slugComponents[0] == Translation::get('categories-slug', 'slug', 'categories')) {
            if (count($slugComponents) == 1) {
                if (View::exists(config('dashed-core.site_theme') . '.categories.index')) {
                    seo()->metaData('metaTitle', Translation::get('all-categories', 'categories', 'All categories'));

                    View::share('productCategory', null);
                    View::share('model', null);
                    $childProductCategories = ProductCategories::getTopLevel(1000);
                    View::share('childProductCategories', $childProductCategories);

                    return view(config('dashed-core.site_theme') . '.categories.index');
                } else {
                    return 'pageNotFound';
                }
            } else {
                array_shift($slugComponents);
                $productCategory = ProductCategory::where('slug->' . App::getLocale(), $slugComponents[0])->first();
                if ($productCategory) {
                    array_shift($slugComponents);
                    foreach ($slugComponents as $slugComponent) {
                        if ($productCategory) {
                            $productCategory = ProductCategory::thisSite()->where('slug->' . App::getLocale(), $slugComponent)->where('parent_id', $productCategory->id)->first();
                        } else {
                            return 'pageNotFound';
                        }
                    }
                    if (View::exists(config('dashed-core.site_theme') . '.categories.index') && $productCategory) {
                        seo()->metaData('metaTitle', $productCategory->metadata && $productCategory->metadata->title ? $productCategory->metadata->title : $productCategory->name);
                        seo()->metaData('metaDescription', $productCategory->metadata->description ?? '');
                        if ($productCategory->metadata && $productCategory->metadata->image) {
                            seo()->metaData('metaImage', $productCategory->metadata->image);
                        }

                        View::share('model', $productCategory);
                        View::share('productCategory', $productCategory);
                        $childProductCategories = $productCategory->getFirstChilds();
                        View::share('childProductCategories', $childProductCategories);

                        return view(config('dashed-core.site_theme') . '.categories.index');
                    } else {
                        return 'pageNotFound';
                    }
                }
            }
        }
    }

    public function globalProductExtras(): BelongsToMany
    {
        return $this->belongsToMany(ProductExtra::class, 'dashed__product_extra_product_category', 'product_category_id', 'product_extra_id')
            ->with(['productExtraOptions']);
    }

    public function globalTabs(): BelongsToMany
    {
        return $this->belongsToMany(ProductTab::class, 'dashed__product_tab_product_category', 'product_category_id', 'product_tab_id')
            ->where('global', 1);
    }

    public function globalFaqs(): BelongsToMany
    {
        return $this->belongsToMany(ProductFaq::class, 'dashed__product_faq_product_category', 'product_category_id', 'product_faq_id')
            ->where('global', 1);
    }
}
