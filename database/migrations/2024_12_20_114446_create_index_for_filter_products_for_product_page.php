<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add composite index to dashed__product_filter
        Schema::table('dashed__product_filter', function (Blueprint $table) {
            $table->index(['product_id', 'product_filter_id', 'product_filter_option_id'], 'product_filter_composite_index');
        });

        // Add index to dashed__product_filters if not already indexed
        Schema::table('dashed__product_filters', function (Blueprint $table) {
            $table->index('id', 'product_filters_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('index_for_filter_products_for_product_page');
    }
};
