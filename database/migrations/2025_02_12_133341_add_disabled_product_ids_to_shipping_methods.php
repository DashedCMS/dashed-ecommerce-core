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
        Schema::create('dashed__shipping_method_disabled_product_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')
                ->constrained('dashed__shipping_methods')
                ->cascadeOnDelete()
                ->name('sm_pg_shipping_method_fk');
            $table->foreignId('product_group_id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete()
                ->name('sm_pg_product_group_fk');
        });

        Schema::create('dashed__shipping_method_disabled_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')
                ->constrained('dashed__shipping_methods')
                ->cascadeOnDelete()
                ->name('sm_p_shipping_method_fk');
            $table->foreignId('product_id')
                ->constrained('dashed__products')
                ->cascadeOnDelete()
                ->name('sm_p_product_fk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
