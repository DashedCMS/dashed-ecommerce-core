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
        Schema::table('dashed__product_groups', function (Blueprint $table) {
            $table->boolean('sync_categories_to_products')
                ->default(1);
        });

        foreach (\Dashed\DashedEcommerceCore\Models\ProductGroup::all() as $productGroup) {
            $productGroup->sync_categories_to_products = true;
            $productGroup->saveQuietly();
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
