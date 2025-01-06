<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (\Dashed\DashedEcommerceCore\Models\ProductGroup::all() as $productGroup) {
            if ($productGroup->products->count() == 1) {
                $product = $productGroup->products->first();
                foreach (\Dashed\DashedCore\Classes\Locales::getLocalesArray() as $key => $locale) {
                    $productGroup->setTranslation('content', $key, $product->getTranslation('content', $key));
                    $productGroup->setTranslation('images', $key, $product->getTranslation('images', $key));
                    $productGroup->setTranslation('description', $key, $product->getTranslation('description', $key));
                    $productGroup->setTranslation('short_description', $key, $product->getTranslation('short_description', $key));
                    $product->setTranslation('images', $key, []);
                    $product->setTranslation('short_description', $key, '');
                    $product->setTranslation('description', $key, '');
                    $product->setTranslation('content', $key, []);
                }
                $productGroup->save();
                $product->save();
            }
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
