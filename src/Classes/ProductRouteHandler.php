<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Artesaos\SEOTools\Facades\SEOTools;
use Qubiqx\QcommerceCore\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Models\Product;

class ProductRouteHandler
{
    public static function handle($parameters = [])
    {
        $slug = $parameters['slug'] ?? '';
        $slugComponents = explode('/', $slug);

        if ($slugComponents[0] == Translation::get('products-slug', 'slug', 'products') && count($slugComponents) == 2) {
            $product = Product::thisSite()->publicShowable()->where('slug->' . App::getLocale(), $slugComponents[1])->first();

            if (!$product) {
                foreach (Product::thisSite()->publicShowable()->get() as $possibleProduct) {
                    if (!$product && $possibleProduct->slug == $slugComponents[1]) {
                        $product = $possibleProduct;
                    }
                }
            }

            if ($product) {
                if (View::exists('qcommerce.products.show')) {
                    SEOTools::setTitle($product->meta_title ?: $product->name);
                    SEOTools::setDescription($product->meta_description);
                    SEOTools::opengraph()->setUrl(url()->current());
                    $metaImage = $product->meta_image;
                    if (!$metaImage) {
                        $metaImage = $product->firstMediaUrl;
                    }
                    if ($metaImage) {
                        SEOTools::addImages($metaImage);
                    }

                    View::share('product', $product);

                    return view('qcommerce.products.show');
                } else {
                    return 'pageNotFound';
                }
            }
        }
    }
}
