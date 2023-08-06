<?php

use Illuminate\Database\Migrations\Migration;

class MigrateProductCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (\Dashed\DashedEcommerceCore\Models\ProductCategory::get() as $productCategory) {
            $activeSiteIds = [];
            foreach ($productCategory->site_ids as $key => $site_id) {
                $activeSiteIds[] = $key;
            }
            $productCategory->site_ids = $activeSiteIds;

            $newContent = [];
            foreach (\Dashed\DashedCore\Classes\Locales::getLocales() as $locale) {
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

//        foreach (\Dashed\DashedCore\Models\MenuItem::withTrashed()->get() as $menuItem) {
//            $menuItem->model = str_replace('Dashed\Dashed\Models\ProductCategory', 'Dashed\DashedEcommerceCore\Models\ProductCategory', $menuItem->model);
//            $siteIds = [];
//            foreach ($menuItem->site_ids as $siteIdKey => $siteId) {
//                $siteIds[] = $siteIdKey;
//            }
//            $menuItem->site_ids = $siteIds;
//            $menuItem->save();
//        }
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
