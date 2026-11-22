<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            // De aankooplimiet voor alle varianten van de productgroep samen.
            if (! Schema::hasColumn('dashed__products', 'limit_purchases_whole_group')) {
                $table->boolean('limit_purchases_whole_group')->default(false);
            }
            // De aankooplimiet ook over eerdere bestellingen van dezelfde
            // klant laten tellen (bijv. "1 Black Friday-box per klant").
            if (! Schema::hasColumn('dashed__products', 'limit_purchases_previous_orders')) {
                $table->boolean('limit_purchases_previous_orders')->default(false);
            }
            // Waaraan we "dezelfde klant" herkennen: email, address en/of ip.
            if (! Schema::hasColumn('dashed__products', 'limit_purchases_match_on')) {
                $table->json('limit_purchases_match_on')->nullable();
            }
            // Alleen bestellingen vanaf dit moment tellen mee (leeg = alle).
            if (! Schema::hasColumn('dashed__products', 'limit_purchases_since')) {
                $table->timestamp('limit_purchases_since')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->dropColumn(['limit_purchases_whole_group', 'limit_purchases_previous_orders', 'limit_purchases_match_on', 'limit_purchases_since']);
        });
    }
};
