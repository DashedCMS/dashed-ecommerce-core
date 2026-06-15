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
     */
    public function up(): void
    {
        Schema::create('dashed__product_crosssell_product_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('dashed__products')->cascadeOnDelete();
            $table->foreignId('product_group_id')->nullable()->constrained('dashed__product_groups')->cascadeOnDelete();
            $table->foreignId('crosssell_product_group_id')->constrained('dashed__product_groups')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__product_crosssell_product_group');
    }
};
