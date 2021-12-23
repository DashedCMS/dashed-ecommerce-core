<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qcommerce__products', function (Blueprint $table) {
            $table->id();

            $table->json('site_ids')->nullable();
            $table->json('name');
            $table->json('slug');
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->integer('weight')->nullable();
            $table->integer('length')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->decimal('price')->nullable();
            $table->decimal('new_price')->nullable();

            $table->string('sku')->nullable();
            $table->string('ean')->nullable();

            $table->boolean('public')->default(true);

            $table->enum('type', ['simple', 'variable', 'external', 'grouped']);

            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();

            $table->string('external_url')->nullable();

            $table->boolean('use_stock')->default(false);
            $table->enum('stock_status', ['in_stock', 'out_of_stock'])->default('in_stock');
            $table->integer('stock')->default(0);
            $table->boolean('low_stock_notification')->default(1);
            $table->integer('low_stock_notification_limit')->default(3);
            $table->boolean('limit_purchases_per_customer')->default(0);
            $table->integer('purchases')->default(0);

            $table->json('content')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();

            $table->foreignId('parent_product_id')->nullable()->constrained('qcommerce__products');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('qcommerce__product_category', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_category_id')->constrained('qcommerce__product_categories');
            $table->foreignId('product_id')->constrained('qcommerce__products');
        });

        Schema::create('qcommerce__product_shipping_class', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipping_class_id')->constrained('qcommerce__shipping_classes');
            $table->foreignId('product_id')->constrained('qcommerce__products');
        });

        Schema::create('qcommerce__product_filter', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_filter_id')->constrained('qcommerce__product_filters');
            $table->foreignId('product_id')->constrained('qcommerce__products');

            //Pivot
            $table->foreignId('product_filter_option_id')->constrained('qcommerce__product_filter_options');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('qcommerce__products');
    }
}
