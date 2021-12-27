<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MigrateProductCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (\Qubiqx\QcommerceEcommerceCore\Models\ProductCategory::get() as $productCategory) {
            $activeSiteIds = [];
            foreach ($productCategory->site_ids as $key => $site_id) {
                $activeSiteIds[] = $key;
            }
            $productCategory->site_ids = $activeSiteIds;

            $newContent = [];
            foreach (\Qubiqx\QcommerceCore\Classes\Locales::getLocales() as $locale) {
                $newBlocks = [];
                foreach (json_decode($productCategory->getTranslation('content', $locale['id']), true) ?: [] as $block) {
                    $newBlocks[\Illuminate\Support\Str::orderedUuid()->toString()] = [
                        'type' => $block['type'],
                        'data' => $block['data'],
                    ];
                }
                $newContent[$locale['id']] = $newBlocks;
            }
            $productCategory->content = $newContent;
            $productCategory->save();
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
