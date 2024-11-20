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
        Schema::table('dashed__product_categories', function (Blueprint $table) {
            $table->integer('order')
                ->default(1);
        });

        foreach(\Dashed\DashedEcommerceCore\Models\ProductCategory::withTrashed()->get() as $index => $category) {
            $category->order = $index + 1;
            $category->save();
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
