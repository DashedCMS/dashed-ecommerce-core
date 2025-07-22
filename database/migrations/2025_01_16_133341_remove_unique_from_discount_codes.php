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
        try {
            Schema::table('dashed__discount_codes', function (Blueprint $table) {
                $table->dropUnique('dashed__discount_codes_code_unique');
            });
        } catch (Exception $exception) {

        }

        try {
            Schema::table('dashed__discount_codes', function (Blueprint $table) {
                $table->dropUnique('qcommerce__discount_codes_code_unique');
            });
        } catch (Exception $exception) {

        }

        Schema::table('dashed__discount_codes', function (Blueprint $table) {
            $table->string('code')
                ->change()
                ->nullable();
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
