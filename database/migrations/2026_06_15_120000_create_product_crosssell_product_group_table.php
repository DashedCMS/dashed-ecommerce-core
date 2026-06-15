<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Cross-sell linking a product OR a product group (owner) to a target
     * product group. Mirrors dashed__product_crosssell_product, which carries a
     * nullable product_id and product_group_id owner; here the target is a group
     * (crosssell_product_group_id) instead of a single product.
     *
     * Foreign keys use short explicit names because the table+column names would
     * otherwise generate identifiers longer than MySQL's 64-character limit.
     */
    public function up(): void
    {
        Schema::create('dashed__product_crosssell_product_group', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_group_id')->nullable();
            $table->unsignedBigInteger('crosssell_product_group_id');
            $table->timestamps();

            $table->foreign('product_id', 'cspg_product_fk')
                ->references('id')->on('dashed__products')->cascadeOnDelete();
            $table->foreign('product_group_id', 'cspg_owner_fk')
                ->references('id')->on('dashed__product_groups')->cascadeOnDelete();
            $table->foreign('crosssell_product_group_id', 'cspg_target_fk')
                ->references('id')->on('dashed__product_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__product_crosssell_product_group');
    }
};
