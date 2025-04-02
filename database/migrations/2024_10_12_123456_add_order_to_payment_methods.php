<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashed__payment_methods', function (Blueprint $table) {
            $table->integer('order')
                ->default(1);
        });

        foreach (\Dashed\DashedEcommerceCore\Models\PaymentMethod::withTrashed()->get() as $index => $paymentMethod) {
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
