<?php

use Illuminate\Database\Migrations\Migration;

class ChangeDiscountCodesSiteIds extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (\Dashed\DashedEcommerceCore\Models\DiscountCode::get() as $discountCode) {
            $activeSiteIds = [];
            foreach ($discountCode->site_ids as $key => $site_id) {
                $activeSiteIds[] = $key;
            }
            $discountCode->site_ids = $activeSiteIds;
            $discountCode->save();
        }
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
}
