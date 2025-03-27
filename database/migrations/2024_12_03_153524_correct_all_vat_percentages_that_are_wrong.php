<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (\Dashed\DashedEcommerceCore\Models\Order::withTrashed()->get() as $order) {
            if ($order->total < 0) {
                $order->vat_percentages = [
                    21 => $order->btw,
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
