<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__product_categories', function (Blueprint $table) {
            $table->string('gs1_classification')->nullable();
            $table->string('gs1_packaging_type')->nullable();
            $table->string('gs1_brand')->nullable();
            $table->string('gs1_sub_brand')->nullable();
            $table->string('gs1_language')->nullable();
            $table->string('gs1_country')->nullable();
            $table->unsignedInteger('gs1_quantity')->nullable();
            $table->string('gs1_unit')->nullable();
            $table->boolean('gs1_consumer_unit')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'gs1_classification',
                'gs1_packaging_type',
                'gs1_brand',
                'gs1_sub_brand',
                'gs1_language',
                'gs1_country',
                'gs1_quantity',
                'gs1_unit',
                'gs1_consumer_unit',
            ]);
        });
    }
};
