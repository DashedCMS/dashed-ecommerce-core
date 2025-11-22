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
            $table->json('variation_index')->nullable();
        });

        foreach (\Dashed\DashedEcommerceCore\Models\ProductGroup::all() as $productGroup) {
            \Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob::dispatch($productGroup, false);
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
