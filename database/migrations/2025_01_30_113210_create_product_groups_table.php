<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasTable('dashed__product_groups')) {
            return;
        }

        Schema::create('dashed__product_groups', function (Blueprint $table) {
            $table->id();

            $table->json('site_ids');

            $table->json('name');
            $table->json('slug');

            $table->boolean('public')
                ->default(true);

            $table->json('images')
                ->nullable();

            $table->decimal('min_price')
                ->nullable();
            $table->decimal('max_price')
                ->nullable();
            $table->integer('total_stock')
                ->default(0);
            $table->integer('total_purchases')
                ->default(0);

            $table->json('short_description');
            $table->json('description');
            $table->json('content');
            $table->json('search_terms');

            $table->integer('order')
                ->default(1);
            $table->integer('only_show_parent_product')
                ->default(0);
            $table->integer('use_parent_stock')
                ->default(0);
            $table->json('missing_variations')
                ->nullable();
            $table->dateTime('start_date')
                ->nullable();
            $table->dateTime('end_date')
                ->nullable();
            $table->foreignId('first_selected_product_id')
                ->nullable()
                ->constrained('dashed__products')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('dashed__products', function (Blueprint $table) {
            $table->foreignId('product_group_id')
                ->after('id')
                ->nullable()
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();

            $table->string('article_code')
                ->after('ean')
                ->nullable();

            $table->boolean('indexable')
                ->default(1);
        });

        Schema::table('dashed__product_category', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->cascadeOnDelete()
                ->change();

            $table->foreignId('product_group_id')
                ->nullable()
                ->after('id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
        });

        Schema::table('dashed__product_characteristic', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->cascadeOnDelete()
                ->change();

            $table->foreignId('product_group_id')
                ->nullable()
                ->after('id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
        });

        Schema::table('dashed__product_crosssell_product', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->cascadeOnDelete()
                ->change();

            $table->foreignId('product_group_id')
                ->nullable()
                ->after('id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
        });

        Schema::table('dashed__product_extra_product', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->cascadeOnDelete()
                ->change();

            $table->foreignId('product_group_id')
                ->nullable()
                ->after('id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
        });

        Schema::table('dashed__product_extras', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->cascadeOnDelete()
                ->change();

            $table->foreignId('product_group_id')
                ->nullable()
                ->after('id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
        });

        Schema::table('dashed__product_suggested_product', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->cascadeOnDelete()
                ->change();

            $table->foreignId('product_group_id')
                ->nullable()
                ->after('id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
        });

        Schema::table('dashed__product_tab_product', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->cascadeOnDelete()
                ->change();

            $table->foreignId('product_group_id')
                ->nullable()
                ->after('id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
        });

        Schema::table('dashed__product_tabs', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('dashed__products')
                ->cascadeOnDelete();

            $table->foreignId('product_group_id')
                ->nullable()
                ->after('id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
        });

        Schema::table('dashed__product_enabled_filter_options', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->cascadeOnDelete()
                ->change();

            $table->foreignId('product_group_id')
                ->nullable()
                ->after('id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
        });

        Schema::table('dashed__active_product_filter', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->cascadeOnDelete()
                ->change();

            $table->foreignId('product_group_id')
                ->nullable()
                ->after('id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
        });

        Schema::table('dashed__product_shipping_class', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->cascadeOnDelete()
                ->change();
        });

        Schema::create('dashed__product_group_volume_discounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_group_id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();

            $table->string('type')
                ->default('percentage');
            $table->decimal('discount_price')
                ->nullable();
            $table->integer('discount_percentage')
                ->nullable();
            $table->integer('min_quantity')
                ->default(1);
            $table->boolean('active_for_all_variants')
                ->default(true);

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('dashed__product_group_volume_discount_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_group_volume_discount_id');
            $table->foreign('product_group_volume_discount_id', 'dpgvd')
                ->references('id')
                ->on('dashed__product_group_volume_discounts')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('dashed__products')
                ->cascadeOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
