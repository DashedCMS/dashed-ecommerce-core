<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeEnumsToStringFieldForDiscountCodes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dashed__discount_codes', function (Blueprint $table) {
            $table->string('valid_for')->nullable()->change();
            $table->string('minimal_requirements')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('string_field_for_discount_codes', function (Blueprint $table) {
            //
        });
    }
}
