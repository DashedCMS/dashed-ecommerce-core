<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->json('meta_image')->after('meta_description')->nullable();
            $table->json('images')->after('description')->nullable();
        });

        foreach (\Dashed\DashedEcommerceCore\Models\Product::get() as $product) {
            $activeSiteIds = [];
            foreach ($product->site_ids as $key => $site_id) {
                $activeSiteIds[] = $key;
            }

            $newContent = [];
            foreach (\Dashed\DashedCore\Classes\Locales::getLocales() as $locale) {
                $newBlocks = [];
                foreach (json_decode($product->getTranslation('content', $locale['id']), true) ?: [] as $block) {
                    $newBlocks[\Illuminate\Support\Str::orderedUuid()->toString()] = [
                        'type' => $block['type'],
                        'data' => $block['data'],
                    ];
                }
                $newContent[$locale['id']] = $newBlocks;

                $media = \Illuminate\Support\Facades\DB::table('media')
                    ->where('model_type', 'Dashed\Dashed\Models\Product')
                    ->where('model_id', $product->id)
                    ->where('collection_name', 'meta-image-' . $locale['id'])
                    ->first();

                if ($media) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists("/dashed/uploads/$media->id/$media->file_name")) {
                        try {
                            \Illuminate\Support\Facades\Storage::disk('public')->copy("/dashed/uploads/$media->id/$media->file_name", "/dashed/products/meta-images/$media->file_name");
                        } catch (Exception $exception) {
                        }
                        $product->setTranslation('meta_image', $locale['id'], "dashed/products/meta-images/$media->file_name");
                        $product->save();
                    }
                }

                $images = [];
                $imageCount = 1;
                $media = \Illuminate\Support\Facades\DB::table('media')
                    ->where('model_type', 'Dashed\Dashed\Models\Product')
                    ->where('model_id', $product->id)
                    ->where('collection_name', 'main-image-' . $locale['id'])
                    ->first();

                if ($media) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists("/dashed/uploads/$media->id/$media->file_name")) {
                        try {
                            \Illuminate\Support\Facades\Storage::disk('public')->copy("/dashed/uploads/$media->id/$media->file_name", "/dashed/products/images/$media->file_name");
                        } catch (Exception $exception) {
                        }

                        $images[] = [
                            'image' => "dashed/products/images/$media->file_name",
                            'alt_text' => is_array(json_decode($media->custom_properties, true)) ? (json_decode($media->custom_properties, true)['alt'] ?? '') : '',
                            'order' => $imageCount,
                        ];
                        $imageCount++;
                    }
                }

                $medias = \Illuminate\Support\Facades\DB::table('media')
                    ->where('model_type', 'Dashed\Dashed\Models\Product')
                    ->where('model_id', $product->id)
                    ->where('collection_name', 'images-' . $locale['id'])
                    ->orderBy('order_column', 'DESC')
                    ->get();

                if ($medias) {
                    foreach ($medias as $media) {
                        if (\Illuminate\Support\Facades\Storage::disk('public')->exists("/dashed/uploads/$media->id/$media->file_name")) {
                            try {
                                \Illuminate\Support\Facades\Storage::disk('public')->copy("/dashed/uploads/$media->id/$media->file_name", "/dashed/products/images/$media->file_name");
                            } catch (Exception $exception) {
                            }

                            $images[] = [
                                'image' => "dashed/products/images/$media->file_name",
                                'alt_text' => is_array(json_decode($media->custom_properties, true)) ? (json_decode($media->custom_properties, true)['alt'] ?? '') : '',
                                'order' => $imageCount,
                            ];
                            $imageCount++;
                        }
                    }
                }

                $product->setTranslation('images', $locale['id'], $images);
                $product->save();
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
