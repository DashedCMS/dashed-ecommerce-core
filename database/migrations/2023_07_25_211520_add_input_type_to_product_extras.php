<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__product_extras', function (Blueprint $table) {
            $table->string('input_type')
                ->default('text');
            $table->integer('min_length')
                ->default(0);
            $table->integer('max_length')
                ->default(255);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_extras', function (Blueprint $table) {
            //
        });
    }
};
