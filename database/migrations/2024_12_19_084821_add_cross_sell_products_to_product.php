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
        Schema::create('dashed__product_crosssell_product', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('crosssell_product_id');

            $table->foreign('product_id', 'product_id_crosssel')->references('id')->on('dashed__products');
            $table->foreign('crosssell_product_id', 'crsel_product_id')->references('id')->on('dashed__products');

            //Pivot
            $table->integer('order')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product', function (Blueprint $table) {
            //
        });
    }
};
