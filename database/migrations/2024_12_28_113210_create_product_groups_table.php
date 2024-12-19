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
        Schema::create('dashed__product_groups', function (Blueprint $table) {
            $table->id();

            $table->json('site_ids');

            $table->json('name');
            $table->json('slug');

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

        \Illuminate\Support\Facades\Artisan::call('dashed:migrate-to-v3');

        try {
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->dropForeign('qcommerce__products_parent_id_foreign');
            });
        } catch (\Exception $e) {
        }
        try {
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->dropForeign('qcommerce__products_parent_product_id_foreign');
            });
        } catch (\Exception $e) {
        }
        try {
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->dropForeign('dashed__products_parent_id_foreign');
            });
        } catch (\Exception $e) {
        }
        try {
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->dropForeign('dashed__products_parent_product_id_foreign');
            });
        } catch (\Exception $e) {
        }

        Schema::table('dashed__products', function (Blueprint $table) {
            $table->dropColumn('parent_id');
            $table->dropColumn('type');
            $table->dropColumn('start_date');
            $table->dropColumn('end_date');
            $table->dropColumn('external_url');
            $table->dropColumn('only_show_parent_product');
            $table->dropColumn('copyable_to_childs');
            $table->dropColumn('missing_variations');
            $table->dropColumn('use_parent_stock');
        });

        try {
            Schema::table('dashed__product_enabled_filter_options', function (Blueprint $table) {
                $table->dropForeign('qcommerce__product_enabled_filter_options_product_id_foreign');
            });
        } catch (\Exception $e) {
        }
        try {
            Schema::table('dashed__product_enabled_filter_options', function (Blueprint $table) {
                $table->dropForeign('dashed__product_enabled_filter_options_product_id_foreign');
            });
        } catch (\Exception $e) {
        }
        Schema::table('dashed__product_enabled_filter_options', function (Blueprint $table) {
            $table->dropColumn('product_id');
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
