<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (\Dashed\DashedEcommerceCore\Models\Product::withTrashed()->get() as $product) {
            $allImages = json_decode($product->getRawOriginal('images'), true);
            foreach($allImages as $key => $images){
                $imageIds = [];
                foreach($images as $image){
                    $imageIds[] = $image['image'];
                }
                $allImages[$key] = $imageIds;
            }
            $product->images = $allImages;
            $product->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_extra_options', function (Blueprint $table) {
            //
        });
    }
};
