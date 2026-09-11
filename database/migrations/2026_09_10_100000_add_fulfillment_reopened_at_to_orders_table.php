<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            // Moment waarop een afgehandelde bestelling voor het laatst is
            // teruggezet naar een niet-afgehandelde status. Zendingen van
            // daarvoor tellen niet meer mee bij het automatisch afhandelen.
            $table->timestamp('fulfillment_reopened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_reopened_at');
        });
    }
};
