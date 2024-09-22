<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->boolean('is_bundle')
                ->default(false);
        });

        Schema::create('dashed__product_bundle_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('dashed__products')
                ->onDelete('cascade');

            $table->foreignId('bundle_product_id')
                ->constrained('dashed__products')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
