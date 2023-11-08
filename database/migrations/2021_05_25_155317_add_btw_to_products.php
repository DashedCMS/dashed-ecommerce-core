<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBtwToProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__order_products', function (Blueprint $table) {
            $table->decimal('btw')->after('price')->default(0);
        });

        //        foreach (\Dashed\DashedEcommerceCore\Models\OrderProduct::get() as $orderProduct) {
        //            $vatPrice = $orderProduct->price / (100 + ($orderProduct->product->vat_rate ?? 21)) * ($orderProduct->product->vat_rate ?? 21);
        //            $orderProduct->btw = $vatPrice;
        //            $orderProduct->save();
        //        }
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
