<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Met welke btw-rekenregels een order is gemaakt (zie Order::VAT_CALCULATION).
 * Bestaande orders houden null: de verzamelfactuur en creditorders rekenen ze
 * op de oude manier, zodat een al aangegeven periode niet verandert.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__orders')) {
            return;
        }

        if (! Schema::hasColumn('dashed__orders', 'vat_calculation')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->unsignedTinyInteger('vat_calculation')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__orders', 'vat_calculation')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->dropColumn('vat_calculation');
            });
        }
    }
};
