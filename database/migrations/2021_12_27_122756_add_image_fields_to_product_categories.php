<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImageFieldsToProductCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('qcommerce__product_categories', function (Blueprint $table) {
            $table->json('image')->nullable()->after('content');
            $table->json('meta_image')->nullable()->after('meta_description');
        });

        foreach (\Qubiqx\QcommerceEcommerceCore\Models\ProductCategory::get() as $productCategory) {
            foreach (\Qubiqx\QcommerceCore\Classes\Locales::getLocales() as $locale) {
                $media = \Illuminate\Support\Facades\DB::table('media')
                    ->where('model_type', 'Qubiqx\Qcommerce\Models\ProductCategory')
                    ->where('model_id', $productCategory->id)
                    ->where('collection_name', 'image-' . $locale['id'])
                    ->first();

                if ($media) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists("/qcommerce/uploads/$media->id/$media->file_name")) {
                        try {
                            \Illuminate\Support\Facades\Storage::disk('public')->copy("/qcommerce/uploads/$media->id/$media->file_name", "/qcommerce/product-categories/images/$media->file_name");
                        } catch (Exception $exception) {

                        }
                        $productCategory->setTranslation('image', $locale['id'], "/qcommerce/product-categories/images/$media->file_name");
                        $productCategory->save();
                    }
                }
            }
        }

        foreach (\Qubiqx\QcommerceEcommerceCore\Models\ProductCategory::get() as $productCategory) {
            foreach (\Qubiqx\QcommerceCore\Classes\Locales::getLocales() as $locale) {
                $media = \Illuminate\Support\Facades\DB::table('media')
                    ->where('model_type', 'Qubiqx\Qcommerce\Models\ProductCategory')
                    ->where('model_id', $productCategory->id)
                    ->where('collection_name', 'meta-image-' . $locale['id'])
                    ->first();

                if ($media) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists("/qcommerce/uploads/$media->id/$media->file_name")) {
                        try {
                            \Illuminate\Support\Facades\Storage::disk('public')->copy("/qcommerce/uploads/$media->id/$media->file_name", "/qcommerce/product-categories/meta-images/$media->file_name");
                        } catch (Exception $exception) {

                        }
                        $productCategory->setTranslation('meta_image', $locale['id'], "/qcommerce/product-categories/meta-images/$media->file_name");
                        $productCategory->save();
                    }
                }
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
        Schema::table('product_categories', function (Blueprint $table) {
            //
        });
    }
}
