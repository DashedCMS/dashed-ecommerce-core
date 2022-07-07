<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (\Qubiqx\QcommerceEcommerceCore\Models\ProductCategory::get() as $model) {
            $content = [];
            foreach (\Qubiqx\QcommerceCore\Classes\Locales::getLocales() as $locale) {
                $content['title'][$locale['id']] = $model->getTranslation('meta_title', $locale['id']);
                $content['description'][$locale['id']] = $model->getTranslation('meta_description', $locale['id']);
                $content['image'][$locale['id']] = $model->getTranslation('meta_image', $locale['id']);
            }
            $model->metadata()->updateOrCreate([], $content);
        }

        Schema::table('qcommerce__product_categories', function (Blueprint $table) {
            $table->dropColumn('meta_title');
            $table->dropColumn('meta_description');
            $table->dropColumn('meta_image');
        });

        foreach (\Qubiqx\QcommerceEcommerceCore\Models\Product::get() as $model) {
            $content = [];
            foreach (\Qubiqx\QcommerceCore\Classes\Locales::getLocales() as $locale) {
                $content['title'][$locale['id']] = $model->getTranslation('meta_title', $locale['id']);
                $content['description'][$locale['id']] = $model->getTranslation('meta_description', $locale['id']);
                $content['image'][$locale['id']] = $model->getTranslation('meta_image', $locale['id']);
            }
            $model->metadata()->updateOrCreate([], $content);
        }

        Schema::table('qcommerce__products', function (Blueprint $table) {
            $table->dropColumn('meta_title');
            $table->dropColumn('meta_description');
            $table->dropColumn('meta_image');
        });
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
};
