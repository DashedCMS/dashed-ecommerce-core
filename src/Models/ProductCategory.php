<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Facades\App;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Classes\FragmentCache;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Models\Concerns\IsVisitable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Dashed\DashedCore\Classes\Caching\CacheInvalidator;
use Dashed\DashedEcommerceCore\Classes\ProductCategories;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Exceptions\HttpResponseException;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
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
//        'image',
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
            FragmentCache::flushTag('product-categories:' . Sites::getActive());

            if (class_exists(CacheInvalidator::class)) {
                CacheInvalidator::forModel($productCategory);
            }
            UpdateProductCategoriesInformationJob::dispatch()->onQueue('ecommerce');

            $categoryIds = collect([$productCategory->id]);

            if ($productCategory->parent_id) {
                $categoryIds->push($productCategory->parent_id);
            }

            $childIds = self::where('parent_id', $productCategory->id)->pluck('id');
            $categoryIds = $categoryIds->merge($childIds)->unique();

            $productGroups = Product::whereHas('productCategories', function ($query) use ($categoryIds) {
                $query->whereIn('dashed__product_categories.id', $categoryIds);
            })->distinct()->pluck('product_group_id');

            foreach ($productGroups as $productGroupId) {
                $productGroup = \Dashed\DashedEcommerceCore\Models\ProductGroup::find($productGroupId);
                if ($productGroup) {
                    UpdateProductInformationJob::dispatch($productGroup, false)->onQueue('ecommerce');
                }
            }
        });

        static::deleted(function ($productCategory) {
            FragmentCache::flushTag('product-categories:' . Sites::getActive());

            if (class_exists(CacheInvalidator::class)) {
                CacheInvalidator::forModel($productCategory);
            }
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

        // Zonder taalprefix was de Engelse URL van een categorie zonder subcategorieën /sd/s6 in plaats van
        // /en/sd/s6: Google vond die als 404 of als redirect via de URL-geschiedenis.
        $url = '/' . ltrim($url, '/');
        if ($locale != Locales::getFirstLocale()['id'] && ! str($url)->startsWith("/{$locale}/")) {
            $url = '/' . $locale . $url;
        }

        return url($url);
    }

    /**
     * Een categorie zonder subcategorieën en zonder producten is een lege pagina; met precies één product is
     * zijn URL die van het product (zie getUrl()), dat al in de sitemap staat. Beide horen er niet in.
     */
    public function showInSitemap(): bool
    {
        // Tellen in de database: een grote categorie heeft duizenden producten.
        return $this->childs()->exists() || $this->products()->limit(2)->count() > 1;
    }

    public function getUrl($locale = null)
    {
        if (! $locale) {
            $locale = app()->getLocale();
        }

        return Cache::rememberForever('product-category-url-' . $this->id . '-' . $locale, function () use ($locale) {
            if (! $this->childs->count()) {
                if ($this->products->count() == 1) {
                    return $this->products->first()->getUrl($locale);
                } else {
                    return $this->getProductsUrl($locale);
                }
            }

            return $this->getIndexUrl($locale);
        });
    }

    /**
     * The address with the categories-slug prefix (/categories/parent/child, /de/kategorien/...), which getUrl()
     * uses for a category with subcategories.
     */
    public function getIndexUrl($locale = null): string
    {
        $originalLocale = app()->getLocale();
        $locale = $locale ?: $originalLocale;

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
    }

    /**
     * resolveRoute() renders every category both with and without the categories-slug prefix, but getUrl() names
     * only one of them. The other address answers with a 301 to the official one, keeping the query string
     * (?page=2, filters), so search engines see a single page. When getUrl() points elsewhere (a category with
     * exactly one product links to that product) nothing changes. Thrown as an HttpResponseException because
     * FrontendController only passes views and 'pageNotFound' through from resolveRoute().
     */
    public function redirectToOfficialUrl(): void
    {
        if (! request()->isMethod('GET') && ! request()->isMethod('HEAD')) {
            return;
        }

        $locale = app()->getLocale();
        $official = static::normalizedPath($this->getUrl($locale));
        $requested = static::normalizedPath(request()->getPathInfo());

        if ($official === $requested) {
            return;
        }

        // Only redirect between the two addresses resolveRoute() serves for this category: never to a product, a
        // stale cached URL or a page this route does not render, so a redirect cannot loop.
        $productsPath = static::normalizedPath($this->getProductsUrl($locale));
        $indexPath = static::normalizedPath($this->getIndexUrl($locale));
        $officialCandidates = [$productsPath];
        if (Customsetting::get('product_category_index_page_enabled', null, true)) {
            $officialCandidates[] = $indexPath;
        }

        if (! in_array($official, $officialCandidates, true) || ! in_array($requested, [$productsPath, $indexPath], true)) {
            return;
        }

        $query = request()->getQueryString();

        throw new HttpResponseException(redirect(url($official) . ($query ? '?' . $query : ''), 301));
    }

    protected static function normalizedPath(?string $url): string
    {
        $path = rawurldecode((string) parse_url((string) $url, PHP_URL_PATH));

        return '/' . trim($path, '/');
    }

    public function hasChilds()
    {
        return (bool)$this->getFirstChilds()->count();
    }

    public function getChilds(): array
    {
        // Cache key is scoped to category PK (global), flush tag is site-scoped via Sites::getActive(). Safe in
        // single-site and deterministic-site-id setups; multi-site consumers that swap dashed_site_id per request
        // should be aware that a cold read on one site will not warm the cache for another site.
        return FragmentCache::remember('product-category-childs-' . $this->id, ['product-categories:' . Sites::getActive()], 3600, function () {
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
        return FragmentCache::remember('product-category-first-childs-' . $this->id, ['product-categories:' . Sites::getActive()], 3600, function () {
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
            if (View::exists(config('dashed-core.site_theme', 'dashed') . '.categories.show') && $productCategory) {
                $productCategory->redirectToOfficialUrl();

                seo()->metaData('metaTitle', $productCategory->metadata && $productCategory->metadata->title ? $productCategory->metadata->title : $productCategory->name);
                seo()->metaData('metaDescription', $productCategory->metadata->description ?? '');
                if ($productCategory->metadata && $productCategory->metadata->image) {
                    seo()->metaData('metaImage', $productCategory->metadata->image);
                }

                View::share('model', $productCategory);
                request()->attributes->set('dashed.current_visitable', $productCategory);
                View::share('productCategory', $productCategory);

                return view(config('dashed-core.site_theme', 'dashed') . '.categories.show');
            } else {
                return 'pageNotFound';
            }
        }

        if (Customsetting::get('product_category_index_page_enabled', null, true) && $slugComponents[0] == Translation::get('categories-slug', 'slug', 'categories')) {
            if (count($slugComponents) == 1) {
                if (View::exists(config('dashed-core.site_theme', 'dashed') . '.categories.index')) {
                    seo()->metaData('metaTitle', Translation::get('all-categories', 'categories', 'All categories'));

                    View::share('productCategory', null);
                    View::share('model', null);
                    $childProductCategories = ProductCategories::getTopLevel(1000);
                    View::share('childProductCategories', $childProductCategories);

                    return view(config('dashed-core.site_theme', 'dashed') . '.categories.index');
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
                    if (View::exists(config('dashed-core.site_theme', 'dashed') . '.categories.index') && $productCategory) {
                        $productCategory->redirectToOfficialUrl();

                        seo()->metaData('metaTitle', $productCategory->metadata && $productCategory->metadata->title ? $productCategory->metadata->title : $productCategory->name);
                        seo()->metaData('metaDescription', $productCategory->metadata->description ?? '');
                        if ($productCategory->metadata && $productCategory->metadata->image) {
                            seo()->metaData('metaImage', $productCategory->metadata->image);
                        }

                        View::share('model', $productCategory);
                        request()->attributes->set('dashed.current_visitable', $productCategory);
                        View::share('productCategory', $productCategory);
                        $childProductCategories = $productCategory->getFirstChilds();
                        View::share('childProductCategories', $childProductCategories);

                        return view(config('dashed-core.site_theme', 'dashed') . '.categories.index');
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
