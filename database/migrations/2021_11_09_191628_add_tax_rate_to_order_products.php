<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTaxRateToOrderProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (\Qubiqx\Qcommerce\Models\OrderProduct::latest()->get() as $orderProduct) {
            if ($orderProduct->product) {
                $orderProduct->vat_rate = $orderProduct->product->vat_rate;
            } else {
                if ($orderProduct->price > 0.00 && $orderProduct->btw == 0.00) {
                    $orderProduct->btw = ($orderProduct->price / 121 * 21);
                    dump($orderProduct->sku);
                }
                $orderProduct->vat_rate = $orderProduct->price > 0.00 ? (round($orderProduct->btw / ($orderProduct->price - $orderProduct->btw), 2) * 100) : 0;
            }
            if (!$orderProduct->sku) {
                if ($orderProduct->product) {
                    $orderProduct->sku = $orderProduct->product->sku;
                } else {
                    $orderProduct->sku = \Illuminate\Support\Str::slug($orderProduct->name);
                }
            }
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
