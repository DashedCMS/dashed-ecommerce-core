<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashed__order_products', function (Blueprint $table) {
            $table->string('fulfillment_provider')
                ->nullable();
            $table->boolean('send_to_fulfiller')
                ->default(false);
        });

        foreach (\Dashed\DashedEcommerceCore\Models\Product::whereNotNull('fulfillment_provider')->get() as $product) {
            \Dashed\DashedEcommerceCore\Models\OrderProduct::where('product_id', $product->id)->update([
                'fulfillment_provider' => $product->fulfillment_provider,
                'send_to_fulfiller' => true,
            ]);
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
