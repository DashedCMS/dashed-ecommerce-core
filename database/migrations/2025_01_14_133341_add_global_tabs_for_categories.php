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
        Schema::create('dashed__product_tab_product_category', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_tab_id')
                ->constrained('dashed__product_tabs')
                ->cascadeOnDelete();
            $table->foreignId('product_category_id')
                ->constrained(
                    'dashed__product_categories',
                    'id',
                    'product_category_tabs_fk'
                )
                ->cascadeOnDelete();

            $table->timestamps();
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
