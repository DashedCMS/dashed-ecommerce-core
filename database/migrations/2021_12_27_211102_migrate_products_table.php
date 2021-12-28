<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MigrateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('qcommerce__products', function (Blueprint $table) {
            $table->json('meta_image')->after('meta_description')->nullable();
            $table->json('image')->after('description')->nullable();
            $table->json('images')->after('image')->nullable();
        });

        foreach (\Qubiqx\QcommerceEcommerceCore\Models\Product::get() as $product) {
            $activeSiteIds = [];
            foreach ($product->site_ids as $key => $site_id) {
                $activeSiteIds[] = $key;
            }

            $newContent = [];
            foreach (\Qubiqx\QcommerceCore\Classes\Locales::getLocales() as $locale) {
                $newBlocks = [];
                foreach (json_decode($product->getTranslation('content', $locale['id']), true) ?: [] as $block) {
                    $newBlocks[\Illuminate\Support\Str::orderedUuid()->toString()] = [
                        'type' => $block['type'],
                        'data' => $block['data'],
                    ];
                }
                $newContent[$locale['id']] = $newBlocks;

                $media = \Illuminate\Support\Facades\DB::table('media')
                    ->where('model_type', 'Qubiqx\Qcommerce\Models\Product')
                    ->where('model_id', $product->id)
                    ->where('collection_name', 'meta-image-' . $locale['id'])
                    ->first();

                if ($media) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists("/qcommerce/uploads/$media->id/$media->file_name")) {
                        try {
                            \Illuminate\Support\Facades\Storage::disk('public')->copy("/qcommerce/uploads/$media->id/$media->file_name", "/qcommerce/products/meta-images/$media->file_name");
                        } catch (Exception $exception) {

                        }
                        $product->setTranslation('meta_image', $locale['id'], "/qcommerce/products/meta-images/$media->file_name");
                        $product->save();
                    }
                }

                //Todo: convert single image
                //Todo: convert multiple images
            }
            $product->content = $newContent;
            $product->site_ids = $activeSiteIds;
            $product->save();
        }
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
