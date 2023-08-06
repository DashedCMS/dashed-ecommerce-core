<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCharacteristicsToProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dashed__product_characteristics', function (Blueprint $table) {
            $table->id();

            $table->json('name');
            $table->integer('order');

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('dashed__product_characteristic', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('dashed__products');
            $table->unsignedBigInteger('product_characteristic_id');
            $table->foreign('product_characteristic_id', 'product_characteristic_id_foreign')->references('id')->on('dashed__product_characteristics');
            $table->json('value');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
}
