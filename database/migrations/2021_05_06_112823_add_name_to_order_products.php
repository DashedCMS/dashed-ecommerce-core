<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNameToOrderProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('qcommerce__order_products', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->foreignId('product_id')->change()->nullable();
        });

        foreach (\Qubiqx\Qcommerce\Models\OrderProduct::get() as $orderProduct) {
            $orderProduct->name = $orderProduct->product ? $orderProduct->product->name : 'Product niet gevonden';
            $orderProduct->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_products', function (Blueprint $table) {
            //
        });
    }
}
