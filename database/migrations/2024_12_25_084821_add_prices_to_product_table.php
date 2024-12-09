<?php

use Dashed\DashedEcommerceCore\Models\ProductExtra;
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
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->decimal('current_price', 10, 2)
                ->after('price')
                ->nullable();
            $table->decimal('discount_price', 10, 2)
                ->after('new_price')
                ->nullable();
        });

        foreach (\Dashed\DashedEcommerceCore\Models\Product::all() as $product) {
            \Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob::dispatch($product);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product', function (Blueprint $table) {
            //
        });
    }
};
