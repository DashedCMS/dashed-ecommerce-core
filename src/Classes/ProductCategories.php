<?php

namespace Qubiqx\QcommerceEcommerceCore\Classes;

use Qubiqx\QcommerceEcommerceCore\Models\ProductCategory;

class ProductCategories
{
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
