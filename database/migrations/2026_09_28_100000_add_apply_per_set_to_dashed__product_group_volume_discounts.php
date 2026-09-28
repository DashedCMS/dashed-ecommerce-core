<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Een volumekorting met apply_per_set geldt alleen voor volle sets van
 * min_quantity stuks, zodat een 1+1-actie (50% vanaf 2) bij 3 stuks niet ook
 * het derde stuk halveert.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('dashed__product_group_volume_discounts', 'apply_per_set')) {
            return;
        }

        Schema::table('dashed__product_group_volume_discounts', function (Blueprint $table) {
            $table->boolean('apply_per_set')
                ->default(false)
                ->after('min_quantity');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dashed__product_group_volume_discounts', 'apply_per_set')) {
            return;
        }

        Schema::table('dashed__product_group_volume_discounts', function (Blueprint $table) {
            $table->dropColumn('apply_per_set');
        });
    }
};
