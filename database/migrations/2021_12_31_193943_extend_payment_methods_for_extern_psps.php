<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ExtendPaymentMethodsForExternPsps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('qcommerce__payment_methods', function (Blueprint $table) {
            $table->boolean('active')->default(1);
            $table->boolean('postpay')->default(0);
            $table->string('psp')->default('own');
            $table->string('psp_id')->nullable();
            $table->string('image')->nullable();
            $table->json('deposit_calculation_payment_method_ids')->nullable();
        });
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
