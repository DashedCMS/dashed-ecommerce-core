<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedEcommerceCore\Classes\Products;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Models\Concerns\IsVisitable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedCore\Models\Concerns\HasCustomBlocks;
use Dashed\DashedEcommerceCore\Classes\ProductCategories;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

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
        'parentProductCategory',
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
            Cache::tags(['product-categories'])->flush();
        });

        static::updated(function ($productCategory) {
            Cache::tags(['product-categories'])->flush();
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

    public function parentProductCategory()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function getProductsUrl()
    {
        $url = $this->slug;
        $parentCategory = $this->parent;
        while ($parentCategory) {
            $url = $parentCategory->slug . '/' . $url;
            $parentCategory = $parentCategory->parent;
        }

        return url($url);
    }

    public function getUrl()
    {
        if (! $this->hasChilds()) {
            if ($this->products->count() == 1) {
                return $this->products->first()->getUrl();
            } else {
                return $this->getProductsUrl();
            }
        }

        $url = $this->slug;
        $parentCategory = $this->parent;
        while ($parentCategory) {
            $url = $parentCategory->slug . '/' . $url;
            $parentCategory = $parentCategory->parent;
        }

        $url = Translation::get('categories-slug', 'slug', 'categories') . '/' . $url;

        return LaravelLocalization::localizeUrl($url);
    }

    public function hasChilds()
    {
        return (bool)$this->getFirstChilds()->count();
    }

    public function getChilds()
    {
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
    }

    public function getFirstChilds()
    {
        return self::with(['products'])->where('parent_id', $this->id)->get();
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'dashed__product_category');
    }

    public static function resolveRoute($parameters = [])
    {
        $slug = $parameters['slug'] ?? '';
        $slugComponents = explode('/', $slug);

        $productCategory = ProductCategory::where('slug->' . App::getLocale(), $slugComponents[0])->first();
        if ($productCategory) {
            array_shift($slugComponents);
            foreach ($slugComponents as $slugComponent) {
                if (! $productCategory) {
                    return 'pageNotFound';
                }
                $productCategory = ProductCategory::thisSite()->where('slug->' . App::getLocale(), $slugComponent)->where('parent_id', $productCategory->id)->first();
            }
            if (View::exists('dashed.categories.show') && $productCategory) {
                seo()->metaData('metaTitle', $productCategory->metadata && $productCategory->metadata->title ? $productCategory->metadata->title : $productCategory->name);
                seo()->metaData('metaDescription', $productCategory->metadata->description ?? '');
                if ($productCategory->metadata && $productCategory->metadata->image) {
                    seo()->metaData('metaImage', $productCategory->metadata->image);
                }

                View::share('productCategory', $productCategory);

                $productsResponse = Products::getAll(request()->get('pagination') ?: Customsetting::get('product_default_amount_of_products', null, 12), request()->get('order-by') ?: Customsetting::get('product_default_order_type', null, 'price'), request()->get('order') ?: Customsetting::get('product_default_order_sort', null, 'DESC'), $productCategory->id);
                View::share('products', $productsResponse['products']);
                View::share('filters', $productsResponse['filters']);

                return view('dashed.categories.show');
            } else {
                return 'pageNotFound';
            }
        }

        if ($slugComponents[0] == Translation::get('categories-slug', 'slug', 'categories')) {
            if (count($slugComponents) == 1) {
                if (View::exists('dashed.categories.index')) {
                    seo()->metaData('metaTitle', Translation::get('all-categories', 'categories', 'All categories'));

                    View::share('productCategory', null);
                    $childProductCategories = ProductCategories::getTopLevel(1000);
                    View::share('childProductCategories', $childProductCategories);

                    return view('dashed.categories.index');
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
                    if (View::exists('dashed.categories.index') && $productCategory) {
                        seo()->metaData('metaTitle', $productCategory->name);

                        View::share('productCategory', $productCategory);
                        $childProductCategories = $productCategory->getFirstChilds();
                        View::share('childProductCategories', $childProductCategories);

                        return view('dashed.categories.index');
                    } else {
                        return 'pageNotFound';
                    }
                }
            }
        }
    }
}
