<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Printstatus per orderregel: wanneer en door wie de regel als
        // "klaar" (geprint/geproduceerd) is gemeld.
        if (! Schema::hasColumn('dashed__order_products', 'production_ready_at')) {
            Schema::table('dashed__order_products', function (Blueprint $table) {
                $table->timestamp('production_ready_at')->nullable();
            });
        }

        if (! Schema::hasColumn('dashed__order_products', 'production_ready_by')) {
            Schema::table('dashed__order_products', function (Blueprint $table) {
                $table->unsignedBigInteger('production_ready_by')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__order_products', 'production_ready_by')) {
            Schema::table('dashed__order_products', function (Blueprint $table) {
                $table->dropIndex(['production_ready_by']);
                $table->dropColumn('production_ready_by');
            });
        }

        if (Schema::hasColumn('dashed__order_products', 'production_ready_at')) {
            Schema::table('dashed__order_products', function (Blueprint $table) {
                $table->dropColumn('production_ready_at');
            });
        }
    }
};
