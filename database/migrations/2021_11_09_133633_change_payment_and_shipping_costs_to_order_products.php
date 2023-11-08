<?php

use Illuminate\Support\Str;
use Dashed\Dashed\Models\Order;
use Dashed\Dashed\Models\OrderProduct;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangePaymentAndShippingCostsToOrderProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__order_products', function (Blueprint $table) {
            $table->decimal('vat_rate')->after('btw')->nullable();
            $table->string('sku')->nullable()->after('product_id');
        });

        //        foreach (Order::get() as $order) {
        //            foreach ($order->orderProducts as $orderProduct) {
        //                $orderProduct->sku = $orderProduct->product ? $orderProduct->product->sku : Str::slug($orderProduct->name);
        //                $orderProduct->save();
        //            }
        //
        //            if ($order->payment_costs > 0.00) {
        //                $orderProduct = new OrderProduct();
        //                $orderProduct->quantity = 1;
        //                $orderProduct->product_id = null;
        //                $orderProduct->order_id = $order->id;
        //                $orderProduct->name = $order->paymentMethod ? $order->paymentMethod->name : $order->payment_method;
        //                $orderProduct->price = $order->payment_costs;
        //                $orderProduct->discount = 0;
        //                $orderProduct->product_extras = json_encode([]);
        //                $orderProduct->sku = 'payment_costs';
        //                $orderProduct->btw = $orderProduct->price / 121 * 21;
        //                $orderProduct->save();
        //
        //                $order->payment_costs = 0;
        //                $order->save();
        //            }
        //
        //            if ($order->shipping_costs > 0.00) {
        //                $orderProduct = new OrderProduct();
        //                $orderProduct->quantity = 1;
        //                $orderProduct->product_id = null;
        //                $orderProduct->order_id = $order->id;
        //                $orderProduct->name = $order->shippingMethod->name;
        //                $orderProduct->price = $order->shipping_costs;
        //                $orderProduct->discount = 0;
        //                $orderProduct->product_extras = json_encode([]);
        //                $orderProduct->sku = 'shipping_costs';
        //                $orderProduct->btw = 0;
        //                $orderProduct->save();
        //
        //                $order->shipping_costs = 0;
        //                $order->save();
        //            }
        //        }

        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropColumn('payment_costs');
            $table->dropColumn('shipping_costs');
        });
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
