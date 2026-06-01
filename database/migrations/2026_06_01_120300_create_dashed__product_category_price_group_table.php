<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('dashed__product_category_price_group');

        Schema::create('dashed__product_category_price_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_group_id')->constrained('dashed__price_groups', 'id', 'pcpg_group_fk')->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained('dashed__product_categories', 'id', 'pcpg_category_fk')->cascadeOnDelete();
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->integer('discount_percentage')->nullable();
            $table->unique(['price_group_id', 'product_category_id'], 'pcpg_group_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__product_category_price_group');
    }
};
