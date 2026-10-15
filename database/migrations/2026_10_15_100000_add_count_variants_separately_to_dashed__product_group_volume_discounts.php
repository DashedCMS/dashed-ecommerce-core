<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Volumekortingen tellen standaard alle varianten uit de productgroep samen
 * (1x M + 1x L = 2 stuks). Met count_variants_separately telt elke
 * winkelwagenregel weer los, zoals het vroeger werkte.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('dashed__product_group_volume_discounts', 'count_variants_separately')) {
            return;
        }

        Schema::table('dashed__product_group_volume_discounts', function (Blueprint $table) {
            $table->boolean('count_variants_separately')
                ->default(false)
                ->after('apply_per_set');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__product_group_volume_discounts', 'count_variants_separately')) {
            return;
        }

        Schema::table('dashed__product_group_volume_discounts', function (Blueprint $table) {
            $table->dropColumn('count_variants_separately');
        });
    }
};
