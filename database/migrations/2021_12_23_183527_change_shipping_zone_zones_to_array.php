<?php

use Illuminate\Database\Migrations\Migration;

class ChangeShippingZoneZonesToArray extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (\Dashed\DashedEcommerceCore\Models\ShippingZone::get() as $shippingZone) {
            $shippingZoneZones = [];
            foreach ($shippingZone->zones as $shippingZoneZone) {
                $shippingZoneZones[] = $shippingZoneZone['id'];
            }

            $shippingZoneDisabledPaymentIds = [];
            if (is_array($shippingZone->disabled_payment_method_ids)) {
                foreach ($shippingZone->disabled_payment_method_ids as $shippingZoneDisabledPaymentId) {
                    $shippingZoneDisabledPaymentIds[] = $shippingZoneDisabledPaymentId['id'];
                }
            }

            $shippingZone->zones = $shippingZoneZones;
            $shippingZone->disabled_payment_method_ids = $shippingZoneDisabledPaymentIds;
            $shippingZone->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
