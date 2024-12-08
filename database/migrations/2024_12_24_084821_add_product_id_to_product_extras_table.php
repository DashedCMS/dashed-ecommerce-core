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
        Schema::table('dashed__product_extras', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->after('id')
                ->nullable()
                ->constrained('dashed__products')
                ->cascadeOnDelete();
        });

        foreach (ProductExtra::withTrashed()->where('global', 0)->get() as $extra) {
            $extra->product_id = $extra->products()->first()->id ?? null;
            $extra->save();
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
