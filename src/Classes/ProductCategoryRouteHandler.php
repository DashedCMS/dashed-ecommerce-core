<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Classes\Locales;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;

class ProductCategoryRouteHandler
{
    public static function handle($parameters = [])
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

    public static function getSitemapUrls(Sitemap $sitemap): Sitemap
    {
        foreach (ProductCategory::thisSite()->get() as $productCategory) {
            foreach (Locales::getLocales() as $locale) {
                if (in_array($locale['id'], Sites::get()['locales'])) {
                    Locales::setLocale($locale['id']);
                    $sitemap
                        ->add(Url::create($productCategory->getUrl()));
                }
            }
        }

        return $sitemap;
    }
}
