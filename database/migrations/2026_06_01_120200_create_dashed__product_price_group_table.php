<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__product_price_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_group_id')->constrained('dashed__price_groups')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('dashed__products')->cascadeOnDelete();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->integer('discount_percentage')->nullable();
            $table->boolean('activated_by_category')->default(false);
            $table->unique(['price_group_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__product_price_group');
    }
};
