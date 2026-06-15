<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Suggested-products linking a product OR a product group (owner) to a target
     * product group. Mirrors dashed__product_suggested_product; the target is a
     * group (suggested_product_group_id) instead of a single product.
     *
     * Short explicit FK names (sspg_*) to stay within MySQL's 64-char limit.
     */
    public function up(): void
    {
        Schema::create('dashed__product_suggested_product_group', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_group_id')->nullable();
            $table->unsignedBigInteger('suggested_product_group_id');
            $table->timestamps();

            $table->foreign('product_id', 'sspg_product_fk')
                ->references('id')->on('dashed__products')->cascadeOnDelete();
            $table->foreign('product_group_id', 'sspg_owner_fk')
                ->references('id')->on('dashed__product_groups')->cascadeOnDelete();
            $table->foreign('suggested_product_group_id', 'sspg_target_fk')
                ->references('id')->on('dashed__product_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__product_suggested_product_group');
    }
};
