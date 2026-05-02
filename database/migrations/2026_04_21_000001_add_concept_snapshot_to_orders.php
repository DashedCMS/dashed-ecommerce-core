<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->json('concept_cart_snapshot')->nullable();
            $table->string('concept_discount_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropColumn(['concept_cart_snapshot', 'concept_discount_code']);
        });
    }
};
