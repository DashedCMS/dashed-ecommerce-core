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
        if (!Schema::hasColumn('dashed__product_groups', 'child_products_count')) {
            Schema::table('dashed__product_groups', function (Blueprint $table) {
                $table->integer('child_products_count')->default(0);
            });
        }

        foreach (\Dashed\DashedEcommerceCore\Models\ProductGroup::all() as $productGroup) {
            $productGroup->child_products_count = $productGroup->products()->count();
            $productGroup->save();
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
