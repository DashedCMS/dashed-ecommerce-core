<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddImageFieldsToProductCategories extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__product_categories', function (Blueprint $table) {
            $table->json('image')->nullable()->after('content');
            $table->json('meta_image')->nullable()->after('meta_description');
        });

        foreach (\Dashed\DashedEcommerceCore\Models\ProductCategory::get() as $productCategory) {
            foreach (\Dashed\DashedCore\Classes\Locales::getLocales() as $locale) {
                $media = \Illuminate\Support\Facades\DB::table('media')
                    ->where('model_type', 'Dashed\Dashed\Models\ProductCategory')
                    ->where('model_id', $productCategory->id)
                    ->where('collection_name', 'image-' . $locale['id'])
                    ->first();

                if ($media) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists("/dashed/uploads/$media->id/$media->file_name")) {
                        try {
                            \Illuminate\Support\Facades\Storage::disk('public')->copy("/dashed/uploads/$media->id/$media->file_name", "/dashed/product-categories/images/$media->file_name");
                        } catch (Exception $exception) {
                        }
                        $productCategory->setTranslation('image', $locale['id'], "/dashed/product-categories/images/$media->file_name");
                        $productCategory->save();
                    }
                }
            }
        }

        foreach (\Dashed\DashedEcommerceCore\Models\ProductCategory::get() as $productCategory) {
            foreach (\Dashed\DashedCore\Classes\Locales::getLocales() as $locale) {
                $media = \Illuminate\Support\Facades\DB::table('media')
                    ->where('model_type', 'Dashed\Dashed\Models\ProductCategory')
                    ->where('model_id', $productCategory->id)
                    ->where('collection_name', 'meta-image-' . $locale['id'])
                    ->first();

                if ($media) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists("/dashed/uploads/$media->id/$media->file_name")) {
                        try {
                            \Illuminate\Support\Facades\Storage::disk('public')->copy("/dashed/uploads/$media->id/$media->file_name", "/dashed/product-categories/meta-images/$media->file_name");
                        } catch (Exception $exception) {
                        }
                        $productCategory->setTranslation('meta_image', $locale['id'], "/dashed/product-categories/meta-images/$media->file_name");
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
