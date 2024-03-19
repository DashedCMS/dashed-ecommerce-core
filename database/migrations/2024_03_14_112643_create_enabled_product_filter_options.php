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
        Schema::create('dashed__product_enabled_filter_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('dashed__products')
                ->cascadeOnDelete();

            $table->foreignId('product_filter_id')
                ->constrained('dashed__product_filters')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('product_filter_option_id');

            $table->foreign('product_filter_option_id', 'dpfoid_dpfo')
                ->references('id')
                ->on('dashed__product_filter_options')
                ->cascadeOnDelete();

            $table->timestamps();
        });

        $options = \Illuminate\Support\Facades\DB::table('dashed__product_filter')->get();
        foreach ($options as $option) {
            $product = \Dashed\DashedEcommerceCore\Models\Product::find($option->product_id);
            if ($product && $product->parent) {
                $product = $product->parent;
            }

            if ($product) {
                \Illuminate\Support\Facades\DB::table('dashed__product_enabled_filter_options')->insert([
                    'product_id' => $product->id,
                    'product_filter_id' => $option->product_filter_id,
                    'product_filter_option_id' => $option->product_filter_option_id,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enabled_product_filter_options');
    }
};
