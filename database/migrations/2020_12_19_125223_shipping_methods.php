<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ShippingMethods extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qcommerce__shipping_zones', function (Blueprint $table) {
            $table->id();

            $table->string('site_id');
            $table->json('name');
            $table->json('zones');

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('qcommerce__shipping_methods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipping_zone_id')->constrained('qcommerce__shipping_zones');
            $table->json('name');
            $table->decimal('costs')->default(0);
            $table->enum('sort', ['free_delivery', 'static_amount', 'take_away']);
            $table->decimal('minimum_order_value')->default(0);
            $table->decimal('maximum_order_value')->default(100);
            $table->integer('order');

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('qcommerce__shipping_method_class', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipping_method_id')->constrained('qcommerce__shipping_methods');
            $table->foreignId('shipping_class_id')->constrained('qcommerce__shipping_classes');
            $table->decimal('costs')->default(0);

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
        Schema::dropIfExists('qcommerce__shipping_methods');
    }
}
