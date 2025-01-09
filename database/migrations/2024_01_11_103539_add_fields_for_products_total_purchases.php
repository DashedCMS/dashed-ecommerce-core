<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->integer('total_purchases')
                ->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
