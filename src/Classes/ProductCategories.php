<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Illuminate\Support\Facades\Cache;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;

class ProductCategories
{
    public static function get($limit = 4, $orderBy = 'created_at', $order = 'DESC')
    {
        return ProductCategory::with(['products'])->limit($limit)->orderBy($orderBy, $order)->get();
    }

    public static function getTopLevel($limit = 4, $orderBy = 'created_at', $order = 'DESC')
    {
//        return Cache::tags(['product-categories'])->rememberForever("product-categories-top-level-$limit-$orderBy-$order", function () use ($limit, $orderBy, $order) {
        return ProductCategory::with(['products'])->where('parent_id', null)->limit($limit)->orderBy($orderBy, $order)->get();
//        });
    }

    public static function getFromIdsWithParents($selectedProductCategoriesIds)
    {
        $selectedProductCategories = ProductCategory::find($selectedProductCategoriesIds);
        foreach ($selectedProductCategories as $selectedProductCategory) {
            $parentProductCategory = ProductCategory::find($selectedProductCategory->parent_category_id);
            while ($parentProductCategory) {
                $selectedProductCategoriesIds[] = $parentProductCategory->id;
                $parentProductCategory = ProductCategory::find($parentProductCategory->parent_category_id);
            }
        }

        return ProductCategory::find($selectedProductCategoriesIds);
    }
}
