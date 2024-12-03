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
        foreach (\Dashed\DashedEcommerceCore\Models\Order::withTrashed()->get() as $order) {
            if (count($order->vat_percentages) < 2) {
                $order->vat_percentages = [
                    21 => $order->btw
                ];
                $order->save();
            }
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
