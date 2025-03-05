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
        Schema::table('dashed__product_extras', function (Blueprint $table) {
            $table->decimal('price', 10, 2)
                ->nullable();
            $table->string('helper_text')
                ->nullable();
        });

        Schema::create('dashed__product_extra_product_category', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_extra_id')
                ->constrained('dashed__product_extras')
                ->cascadeOnDelete();
            $table->foreignId('product_category_id')
                ->constrained(
                    'dashed__product_categories',
                    'id',
                    'product_category_fk'
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
