<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $products = \Dashed\DashedEcommerceCore\Models\Product::withTrashed()->get();
        foreach ($products as $product) {
            $product->images = $product->images[app()->getLocale()] ?? [];
            $product->saveQuietly();
        }

        $productGroups = \Dashed\DashedEcommerceCore\Models\ProductGroup::withTrashed()->get();
        foreach ($productGroups as $productGroup) {
            $productGroup->images = $productGroup->images[app()->getLocale()] ?? [];
            $productGroup->saveQuietly();
        }

        $productCategories = \Dashed\DashedEcommerceCore\Models\ProductCategory::withTrashed()->get();
        foreach ($productCategories as $productCategory) {
            $productCategory->image = $productCategory->image ? (json_decode($productCategory->image, true)[app()->getLocale()] ?? null) : null;
            $productCategory->saveQuietly();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
