<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Artesaos\SEOTools\Facades\SEOTools;
use Qubiqx\QcommerceCore\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;

class ProductCategoryRouteHandler
{
    public static function handle($parameters = [])
    {
        $slug = $parameters['slug'] ?? '';
        $slugComponents = explode('/', $slug);

        if ($slugComponents[0] == Translation::get('categories-slug', 'slug', 'categories')) {
            if (count($slugComponents) == 1) {
                if (View::exists('qcommerce.categories.index')) {
                    SEOTools::setTitle(Translation::get('all-categories', 'categories', 'All categories'));
                    SEOTools::opengraph()->setUrl(url()->current());

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
                        $productCategory = ProductCategory::thisSite()->where('slug->' . App::getLocale(), $slugComponent)->where('parent_category_id', $productCategory->id)->first();
                    }
                    if (View::exists('qcommerce.categories.index') && $productCategory) {
                        SEOTools::setTitle($productCategory->name);
                        SEOTools::opengraph()->setUrl(url()->current());

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
