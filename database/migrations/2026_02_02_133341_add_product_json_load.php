<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashed__product_feed_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('locale', 10);
            $table->json('payload'); // of json() als je MySQL json wil
            $table->timestamps();

            $table->unique(['product_id', 'locale']);
            $table->index(['locale', 'product_id']);

            $table->foreign('product_id')->references('id')->on('dashed__products')->onDelete('cascade');
        });


        foreach(\Dashed\DashedEcommerceCore\Models\ProductGroup::all() as $productGroup) {
            \Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob::dispatch($productGroup, false)->onQueue('ecommerce');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
