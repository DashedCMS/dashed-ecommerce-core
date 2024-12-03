<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach(\Dashed\DashedEcommerceCore\Models\Order::withTrashed()->get() as $order){
            if($order->total < 0 && $order->discount > 0){
                $order->discount = $order->discount * -1;
                $order->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('minus_number', function (Blueprint $table) {
            //
        });
    }
};
