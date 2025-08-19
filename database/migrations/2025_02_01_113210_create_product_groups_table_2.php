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
        if (! Schema::hasColumn('dashed__products', 'type')) {
            return;
        }

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
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->dropColumn('efulfillment_shop_id');
            });
        } catch (Exception $e) {

        }

        try {
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->dropColumn('efulfillment_shop_error');
            });
        } catch (Exception $e) {

        }

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

        foreach (\Dashed\DashedEcommerceCore\Models\ProductGroup::all() as $productGroup) {
            if ($productGroup->products->count() == 1) {
                $product = $productGroup->products->first();
                foreach (\Dashed\DashedCore\Classes\Locales::getLocalesArray() as $key => $locale) {
                    $productGroup->setTranslation('content', $key, $product->getTranslation('content', $key));
                    $productGroup->setTranslation('images', $key, $product->getTranslation('images', $key));
                    $productGroup->setTranslation('description', $key, $product->getTranslation('description', $key));
                    $productGroup->setTranslation('short_description', $key, $product->getTranslation('short_description', $key));
                    $product->setTranslation('images', $key, []);
                    $product->setTranslation('short_description', $key, '');
                    $product->setTranslation('description', $key, '');
                    $product->setTranslation('content', $key, []);
                }
                $productGroup->save();
                $product->save();
            }
        }
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
