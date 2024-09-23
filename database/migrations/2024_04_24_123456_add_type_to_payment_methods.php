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
        Schema::table('dashed__payment_methods', function (Blueprint $table) {
            $table->string('type')
                ->default('online')
                ->after('name');
            $table->boolean('is_cash_payment')
                ->default(false)
                ->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_extra_options', function (Blueprint $table) {
            //
        });
    }
};
