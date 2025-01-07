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
        Schema::create('dashed__product_group_volume_discounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_group_id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();

            $table->string('type')
                ->default('percentage');
            $table->decimal('discount_price')
                ->nullable();
            $table->integer('discount_percentage')
                ->nullable();
            $table->integer('min_quantity')
                ->default(1);
            $table->boolean('active_for_all_variants')
                ->default(true);

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('dashed__product_group_volume_discount_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_group_volume_discount_id');
            $table->foreign('product_group_volume_discount_id', 'dpgvd')
                ->references('id')
                ->on('dashed__product_group_volume_discounts')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('dashed__products')
                ->cascadeOnDelete();
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
