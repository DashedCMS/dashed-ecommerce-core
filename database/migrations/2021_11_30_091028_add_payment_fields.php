<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qcommerce__order_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('qcommerce__orders');
            $table->string('psp')->nullable();
            $table->string('psp_id')->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained('qcommerce__payment_methods');
            $table->string('payment_method')->nullable();
            $table->string('psp_payment_method_id')->nullable();
            $table->decimal('amount')->default(0);
            $table->string('status')->default('pending');
            $table->string('hash')->nullable();

            $table->timestamps();
        });

        foreach(\Qubiqx\QcommerceEcommerceCore\Models\Order::get() as $order){
            if($order->total > 0.00){
                $order->orderPayments()->create([
                    'psp' => $order->psp,
                    'psp_id' => $order->psp_id,
                    'payment_method' => $order->getRawOriginal('payment_method'),
                    'payment_method_id' => $order->payment_method_id,
                    'amount' => $order->total,
                    'status' => $order->status,
                ]);
            }
        }

        Schema::table('qcommerce__orders', function (Blueprint $table) {
            $table->dropColumn('psp');
            $table->dropColumn('psp_id');
            $table->dropColumn('payment_method');
            $table->dropForeign('qcommerce__orders_payment_method_id_foreign');
            $table->dropColumn('payment_method_id');
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
