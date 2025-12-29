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
        Schema::table('dashed__product_filters', function (Blueprint $table) {
            $table->boolean('use_stock')->default(0);
        });

        Schema::table('dashed__product_filter_options', function (Blueprint $table) {
            $table->boolean('in_stock')
                ->default(1);
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
