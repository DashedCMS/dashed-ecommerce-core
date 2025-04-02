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
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->dateTime('start_date')
                ->nullable();
            $table->dateTime('end_date')
                ->nullable();
        });

        Schema::table('dashed__product_groups', function (Blueprint $table) {
            $table->dateTime('start_date')
                ->nullable();
            $table->dateTime('end_date')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
