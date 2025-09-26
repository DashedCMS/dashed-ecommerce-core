<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->json('product_search_terms')
                ->after('search_terms')
                ->nullable();
        });

        foreach (\Dashed\DashedEcommerceCore\Models\Product::all() as $product) {
            foreach (\Dashed\DashedCore\Classes\Locales::getLocalesArray() as $locale => $language) {
                $product->setTranslation('product_search_terms', $locale, ($product->productGroup->getTranslation('search_terms', $locale) ?? '') . ' ' . $product->getTranslation('search_terms', $locale));
            }
            $product->save();
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
