<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_source_product_id')->nullable()->after('use_stock');
            $table->foreign('stock_source_product_id')
                ->references('id')
                ->on('dashed__products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->dropForeign(['stock_source_product_id']);
            $table->dropColumn('stock_source_product_id');
        });
    }
};
