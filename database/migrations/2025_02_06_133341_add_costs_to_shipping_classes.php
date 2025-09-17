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
        Schema::table('dashed__shipping_classes', function (Blueprint $table) {
            $table->decimal('price', 10, 2)
                ->nullable();
            $table->boolean('count_per_product')
                ->default(false);
            $table->boolean('count_once')
                ->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
