<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__order_return_lines')) {
            return;
        }

        Schema::create('dashed__order_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained('dashed__order_returns')->cascadeOnDelete();
            $table->foreignId('order_product_id')->constrained('dashed__order_products');
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('return_reason_id')->nullable()->constrained('dashed__return_reasons')->nullOnDelete();
            $table->text('reason_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_return_lines');
    }
};
