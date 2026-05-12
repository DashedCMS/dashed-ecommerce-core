<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__product_co_purchase')) {
            return;
        }

        Schema::create('dashed__product_co_purchase', function (Blueprint $table) {
            $table->id();
            // Canonical order: product_a_id < product_b_id (enforced at
            // write-time by PrecomputeCoPurchaseScoresJob, not at DB-level).
            $table->unsignedBigInteger('product_a_id');
            $table->unsignedBigInteger('product_b_id');
            $table->unsignedInteger('co_count')->default(0);
            $table->decimal('score', 5, 4)->default(0);
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_a_id', 'product_b_id'], 'uniq_co_purchase_pair');
            $table->index(['product_a_id', 'score']);
            $table->index(['product_b_id', 'score']);

            $table->foreign('product_a_id')
                ->references('id')->on('dashed__products')
                ->cascadeOnDelete();
            $table->foreign('product_b_id')
                ->references('id')->on('dashed__products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__product_co_purchase');
    }
};
