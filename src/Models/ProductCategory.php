<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Classes\Products;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Qubiqx\QcommerceCore\Models\Concerns\IsVisitable;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Qubiqx\QcommerceEcommerceCore\Classes\ProductCategories;

class ProductCategory extends Model
{
    use SoftDeletes;
    use IsVisitable;

    protected static $logFillable = true;

    protected $table = 'qcommerce__product_categories';

    public $translatable = [
        'name',
        'slug',
        'content',
        'image',
        'meta_image',
        'meta_title',
        'meta_description',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $with = [
        'parentProductCategory',
    ];
    protected $casts = [
        'site_ids' => 'array',
        'content' => 'array',
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
        return $this->belongsTo(self::class, 'parent_category_id');
    }

    public function getProductsUrl()
    {
        $url = $this->slug;
        $parentCategory = $this->parentCategory;
        while ($parentCategory) {
            $url = $parentCategory->slug . '/' . $url;
            $parentCategory = $parentCategory->parentCategory;
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
        $parentCategory = $this->parentCategory;
        while ($parentCategory) {
            $url = $parentCategory->slug . '/' . $url;
            $parentCategory = $parentCategory->parentCategory;
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
        $childProductCategories = self::where('parent_category_id', $this->id)->get();
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
        return $this->belongsToMany(Product::class, 'qcommerce__product_category');
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
                $productCategory = ProductCategory::thisSite()->where('slug->' . App::getLocale(), $slugComponent)->where('parent_category_id', $productCategory->id)->first();
            }
            if (View::exists('qcommerce.categories.show') && $productCategory) {
                seo()->metaData('metaTitle', $productCategory->metadata && $productCategory->metadata->title ? $productCategory->metadata->title : $productCategory->name);
                seo()->metaData('metaDescription', $productCategory->metadata->description ?? '');
                if ($productCategory->metadata && $productCategory->metadata->image) {
                    seo()->metaData('metaImage', $productCategory->metadata->image);
                }

                View::share('productCategory', $productCategory);

                $productsResponse = Products::getAll(request()->get('pagination') ?: Customsetting::get('product_default_amount_of_products', null, 12), request()->get('order-by') ?: Customsetting::get('product_default_order_type', null, 'price'), request()->get('order') ?: Customsetting::get('product_default_order_sort', null, 'DESC'), $productCategory->id);
                View::share('products', $productsResponse['products']);
                View::share('filters', $productsResponse['filters']);

                return view('qcommerce.categories.show');
            } else {
                return 'pageNotFound';
            }
        }

        if ($slugComponents[0] == Translation::get('categories-slug', 'slug', 'categories')) {
            if (count($slugComponents) == 1) {
                if (View::exists('qcommerce.categories.index')) {
                    seo()->metaData('metaTitle', Translation::get('all-categories', 'categories', 'All categories'));

                    View::share('productCategory', null);
                    $childProductCategories = ProductCategories::getTopLevel(1000);
                    View::share('childProductCategories', $childProductCategories);

                    return view('qcommerce.categories.index');
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
                            $productCategory = ProductCategory::thisSite()->where('slug->' . App::getLocale(), $slugComponent)->where('parent_category_id', $productCategory->id)->first();
                        } else {
                            return 'pageNotFound';
                        }
                    }
                    if (View::exists('qcommerce.categories.index') && $productCategory) {
                        seo()->metaData('metaTitle', $productCategory->name);

                        View::share('productCategory', $productCategory);
                        $childProductCategories = $productCategory->getFirstChilds();
                        View::share('childProductCategories', $childProductCategories);

                        return view('qcommerce.categories.index');
                    } else {
                        return 'pageNotFound';
                    }
                }
            }
        }
    }
}
