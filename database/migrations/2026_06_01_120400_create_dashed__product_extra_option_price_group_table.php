<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('dashed__product_extra_option_price_group');

        Schema::create('dashed__product_extra_option_price_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_group_id')->constrained('dashed__price_groups', 'id', 'peopg_group_fk')->cascadeOnDelete();
            $table->foreignId('product_extra_option_id')->constrained('dashed__product_extra_options', 'id', 'peopg_option_fk')->cascadeOnDelete();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('discount_percentage')->nullable();
            $table->unique(['price_group_id', 'product_extra_option_id'], 'peo_price_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__product_extra_option_price_group');
    }
};
