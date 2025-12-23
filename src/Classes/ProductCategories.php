<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class ProductCategories
{
    public static function get($limit = 4, $orderBy = null, $order = null)
    {
        if(!$orderBy){
            $orderBy = Customsetting::get('product_categories_default_order_by', default: 'created_at');
        }
        if(!$order){
            $order = Customsetting::get('product_categories_default_order', default: 'DESC');
        }
        return ProductCategory::with(['products'])->limit($limit)->orderBy($orderBy, $order)->get();
    }

    public static function getTopLevel($limit = 4, $orderBy = null, $order = null)
    {
        if(!$orderBy){
            $orderBy = Customsetting::get('product_categories_default_order_by', default: 'created_at');
        }
        if(!$order){
            $order = Customsetting::get('product_categories_default_order', default: 'DESC');
        }
        //        return Cache::tags(['product-categories'])->rememberForever("product-categories-top-level-$limit-$orderBy-$order", function () use ($limit, $orderBy, $order) {
        return ProductCategory::with(['products'])->where('parent_id', null)->limit($limit)->orderBy($orderBy, $order)->get();
        //        });
    }

    public static function getFromIdsWithParents($selectedProductCategoriesIds)
    {
        $selectedProductCategories = ProductCategory::find($selectedProductCategoriesIds);
        foreach ($selectedProductCategories as $selectedProductCategory) {
            $parentProductCategory = ProductCategory::find($selectedProductCategory->parent_id);
            while ($parentProductCategory) {
                $selectedProductCategoriesIds[] = $parentProductCategory->id;
                $parentProductCategory = ProductCategory::find($parentProductCategory->parent_id);
            }
        }

        return ProductCategory::find($selectedProductCategoriesIds);
    }
}
