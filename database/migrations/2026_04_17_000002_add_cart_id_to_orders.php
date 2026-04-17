<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->unsignedBigInteger('cart_id')->nullable()->after('user_id');
            $table->foreign('cart_id')
                ->references('id')
                ->on('dashed__carts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropForeign(['cart_id']);
            $table->dropColumn('cart_id');
        });
    }
};
