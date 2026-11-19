<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * De taal waarin een klant zich aanmeldde voor "terug op voorraad", zodat de
 * mail en de productlink in die taal gaan en niet in de standaardtaal.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__stock_notifications') && ! Schema::hasColumn('dashed__stock_notifications', 'locale')) {
            Schema::table('dashed__stock_notifications', function (Blueprint $table) {
                $table->string('locale', 10)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__stock_notifications', 'locale')) {
            Schema::table('dashed__stock_notifications', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }
};
