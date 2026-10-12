<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            // De drempel voor gratis verzending op het moment van bestellen;
            // null = geen gratis verzending gekregen of order van voor deze kolom.
            $table->decimal('free_shipping_threshold', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropColumn('free_shipping_threshold');
        });
    }
};
