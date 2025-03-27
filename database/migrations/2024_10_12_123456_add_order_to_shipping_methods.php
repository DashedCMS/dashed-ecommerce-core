<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (\Dashed\DashedEcommerceCore\Models\ShippingMethod::withTrashed()->get() as $index => $shippingMethod) {
            $shippingMethod->order = $index + 1;
            $shippingMethod->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
