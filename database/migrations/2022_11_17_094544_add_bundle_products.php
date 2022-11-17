<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('qcommerce__products', function (Blueprint $table) {
            $table->boolean('is_bundle')
                ->default(false);
        });

        Schema::create('qcommerce__product_bundle_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('qcommerce__products')
                ->onDelete('cascade');

            $table->foreignId('bundle_product_id')
                ->constrained('qcommerce__products')
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
