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
        Schema::create('dashed__product_tabs', function (Blueprint $table) {
            $table->id();

            $table->json('name');
            $table->json('content');
            $table->integer('order')
                ->default(0);
            $table->boolean('global')
                ->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dashed__product_tab_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('dashed__products')
                ->cascadeOnDelete();
            $table->foreignId('tab_id')
                ->constrained('dashed__product_tabs')
                ->cascadeOnDelete();
        });
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
