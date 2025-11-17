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
        Schema::create('dashed__payment_method_shipping_method', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('payment_method_id');
            $table->unsignedBigInteger('shipping_method_id');

            $table->foreign('payment_method_id', 'pm_sm_pm_fk')
                ->references('id')
                ->on('dashed__payment_methods')
                ->cascadeOnDelete();

            $table->foreign('shipping_method_id', 'pm_sm_sm_fk')
                ->references('id')
                ->on('dashed__shipping_methods')
                ->cascadeOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
