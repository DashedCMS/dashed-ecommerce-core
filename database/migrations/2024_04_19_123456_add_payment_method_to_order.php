<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->foreignId('payment_method_id')
                ->after('shipping_method_id')
                ->nullable()
                ->constrained('dashed__payment_methods')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_extra_options', function (Blueprint $table) {
            //
        });
    }
};
