<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;

/**
 * Dupliceert een product mét de relevante relaties (categorieën, verzendklassen,
 * bundels, kenmerken, filters, gesuggereerde/cross-sell producten, extra's,
 * custom blocks en metadata). Gedeeld door de Filament-actie én de mobile-api,
 * zodat er maar één bron van waarheid is.
 */
class ProductDuplicator
{
    public static function duplicate(Product $source): Product
    {
        $newProduct = $source->replicate();
        $newProduct->purchases = 0;
        $newProduct->sku = 'SKU'.rand(10000, 99999);
        foreach (Locales::getLocales() as $locale) {
            $newProduct->setTranslation('slug', $locale['id'], $newProduct->getTranslation('slug', $locale['id']));
            while (Product::where('slug->'.$locale['id'], $newProduct->getTranslation('slug', $locale['id']))->count()) {
                $newProduct->setTranslation('slug', $locale['id'], $newProduct->getTranslation('slug', $locale['id']).Str::random(1));
            }
        }
        $newProduct->save();

        $source->load('productCategories', 'shippingClasses', 'productFilters', 'productCharacteristics', 'productExtras');

        $newProduct->productCategories()->sync($source->productCategories);
        $newProduct->shippingClasses()->sync($source->shippingClasses);
        $newProduct->bundleProducts()->sync($source->bundleProducts);

        $characteristics = DB::table('dashed__product_characteristic')->where('product_id', $source->id)->whereNull('deleted_at')->get();
        if ($characteristics->isNotEmpty()) {
            DB::table('dashed__product_characteristic')->insert($characteristics->map(fn ($c) => [
                'product_id' => $newProduct->id,
                'product_characteristic_id' => $c->product_characteristic_id,
                'value' => $c->value,
            ])->all());
        }

        $filters = DB::table('dashed__product_filter')->where('product_id', $source->id)->get();
        if ($filters->isNotEmpty()) {
            DB::table('dashed__product_filter')->insert($filters->map(fn ($f) => [
                'product_id' => $newProduct->id,
                'product_filter_id' => $f->product_filter_id,
                'product_filter_option_id' => $f->product_filter_option_id,
            ])->all());
        }

        $suggested = DB::table('dashed__product_suggested_product')->where('product_id', $source->id)->get();
        if ($suggested->isNotEmpty()) {
            DB::table('dashed__product_suggested_product')->insert($suggested->map(fn ($s) => [
                'product_id' => $newProduct->id,
                'suggested_product_id' => $s->suggested_product_id,
                'order' => $s->order,
            ])->all());
        }

        $crossSell = DB::table('dashed__product_crosssell_product')->where('product_id', $source->id)->get();
        if ($crossSell->isNotEmpty()) {
            DB::table('dashed__product_crosssell_product')->insert($crossSell->map(fn ($c) => [
                'product_id' => $newProduct->id,
                'crosssell_product_id' => $c->crosssell_product_id,
                'order' => $c->order,
            ])->all());
        }

        foreach (DB::table('dashed__product_extras')->where('product_id', $source->id)->whereNull('deleted_at')->get() as $productExtra) {
            $newProductExtra = new ProductExtra();
            $newProductExtra->product_id = $newProduct->id;
            foreach (json_decode($productExtra->name, true) as $locale => $name) {
                $newProductExtra->setTranslation('name', $locale, $name);
            }
            $newProductExtra->type = $productExtra->type;
            $newProductExtra->required = $productExtra->required;
            $newProductExtra->save();

            foreach (DB::table('dashed__product_extra_options')->where('product_extra_id', $productExtra->id)->whereNull('deleted_at')->get() as $productExtraOption) {
                DB::table('dashed__product_extra_options')->insert([
                    'product_extra_id' => $newProductExtra->id,
                    'value' => $productExtraOption->value,
                    'price' => $productExtraOption->price,
                ]);
            }
        }

        if ($source->customBlocks) {
            $newCustomBlock = $source->customBlocks->replicate();
            $newCustomBlock->blockable_id = $newProduct->id;
            $newCustomBlock->save();
        }

        if ($source->metaData) {
            $newMetaData = $source->metaData->replicate();
            $newMetaData->metadatable_id = $newProduct->id;
            $newMetaData->save();
        }

        UpdateProductInformationJob::dispatch($newProduct->productGroup)->onQueue('ecommerce');

        return $newProduct;
    }
}
