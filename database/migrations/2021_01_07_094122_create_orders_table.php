<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qcommerce__orders', function (Blueprint $table) {
            $table->id();

            $table->ipAddress('ip');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('street')->nullable();
            $table->string('house_nr')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->boolean('marketing')->default(0);

            $table->string('company_name')->nullable();
            $table->string('btw_id')->nullable();
            $table->text('note')->nullable();

            $table->string('hash')->unique()->nullable();

            $table->string('invoice_street')->nullable();
            $table->string('invoice_house_nr')->nullable();
            $table->string('invoice_zip_code')->nullable();
            $table->string('invoice_city')->nullable();
            $table->string('invoice_country')->nullable();

            $table->string('invoice_id')->unique()->nullable();
            $table->string('psp')->nullable();
            $table->string('psp_id')->unique()->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('total')->nullable();
            $table->decimal('subtotal')->nullable();
            $table->decimal('btw')->nullable();
            $table->decimal('discount')->nullable();
            $table->decimal('shipping_costs')->nullable();
            $table->string('status')->default('pending');//pending, cancelled, paid, waiting_for_confirmation
            $table->boolean('invoice_send_to_customer')->default(0);
            $table->string('ga_user_id')->nullable();
            $table->boolean('ga_commerce_hit_send')->default(0);
            $table->string('order_origin')->default('own');

            $table->string('site_id');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('discount_code_id')->nullable()->constrained('qcommerce__discount_codes');
            $table->foreignId('shipping_method_id')->nullable()->constrained('qcommerce__shipping_methods');
            $table->foreignId('payment_method_id')->nullable()->constrained('qcommerce__payment_methods');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('qcommerce__order_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('qcommerce__orders');
            $table->foreignId('product_id')->constrained('qcommerce__products');

            $table->decimal('price')->nullable();
            $table->decimal('discount')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
