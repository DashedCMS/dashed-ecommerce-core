<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddExtraOptionsToProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qcommerce__product_extras', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('qcommerce__products');
            $table->json('name');
            $table->string('type')->default('single');
            $table->boolean('required')->default(1);

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('qcommerce__product_extra_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_extra_id')->constrained('qcommerce__product_extras');
            $table->json('value');
            $table->decimal('price');

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
