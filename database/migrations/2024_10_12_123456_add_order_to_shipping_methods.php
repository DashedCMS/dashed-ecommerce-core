<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach(\Dashed\DashedEcommerceCore\Models\ShippingMethod::withTrashed()->get() as $index => $paymentMethod) {
            $paymentMethod->order = $index + 1;
            $paymentMethod->save();
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
