<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveExcessFromOtherPackagesFromOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //        Schema::table('dashed__orders', function (Blueprint $table) {
        //            $table->dropColumn('keen_delivery_shipment_id');
        //            $table->dropColumn('keen_delivery_label');
        //            $table->dropColumn('keen_delivery_label_url');
        //            $table->dropColumn('keen_delivery_label_printed');
        //            $table->dropColumn('keen_delivery_track_and_trace');
        //            $table->dropColumn('pushable_to_efulfillment_shop');
        //            $table->dropColumn('pushed_to_efulfillment_shop');
        //            $table->dropColumn('efulfillment_shop_error');
        //            $table->dropColumn('efulfillment_shop_invoice_address_id');
        //            $table->dropColumn('efulfillment_shop_shipping_address_id');
        //            $table->dropColumn('efulfillment_shop_sale_id');
        //            $table->dropColumn('efulfillment_shop_track_and_trace');
        //            $table->dropColumn('efulfillment_shop_fulfillment_status');
        //            $table->dropForeign('qcommerce__orders_channable_order_connection_id_foreign');
        //            $table->dropColumn('channable_order_connection_id');
        //        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('other_packages_from_order', function (Blueprint $table) {
            //
        });
    }
}
