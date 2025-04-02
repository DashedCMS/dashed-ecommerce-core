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
        Schema::table('dashed__product_filter', function (Blueprint $table) {
            $table->index(['product_id', 'product_filter_option_id'], 'product_filter_composite_idx');
        });

        Schema::table('dashed__product_filter_options', function (Blueprint $table) {
            $table->index('product_filter_id', 'product_filter_options_filter_idx');
        });

        Schema::table('dashed__product_filters', function (Blueprint $table) {
            $table->index(['hide_filter_on_overview_page', 'created_at'], 'product_filters_hide_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_filter_indexes');
    }
};
