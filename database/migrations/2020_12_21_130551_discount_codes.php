<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DiscountCodes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qcommerce__discount_codes', function (Blueprint $table) {
            $table->id();

            $table->json('site_ids');
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('limit_use_per_customer')->default(0);
            $table->boolean('use_stock')->default(0);
            $table->integer('stock')->nullable();
            $table->integer('stock_used')->default(0);
            $table->enum('minimal_requirements', ['products', 'amount'])->nullable();
            $table->decimal('minimum_amount')->nullable();
            $table->integer('minimum_products_count')->nullable();
            $table->enum('type', ['percentage', 'amount'])->default('percentage');
            $table->integer('discount_percentage')->nullable();
            $table->decimal('discount_amount')->nullable();
            $table->enum('valid_for', ['categories', 'products'])->nullable();
            $table->enum('valid_for_customers', ['all', 'specific'])->default('all');
            $table->json('valid_customers')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('qcommerce__discount_category', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_category_id')->constrained('qcommerce__product_categories');
            $table->foreignId('discount_code_id')->constrained('qcommerce__discount_codes');
        });

        Schema::create('qcommerce__discount_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('qcommerce__products');
            $table->foreignId('discount_code_id')->constrained('qcommerce__discount_codes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('qcommerce__discount_codes');
    }
}
