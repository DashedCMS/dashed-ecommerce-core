<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Marker-kolommen voor de dedup/reset-logica van StockRuleScanner
     * (stock.low / stock.back). Puur uit de AutomationRuleRun-historie is
     * "stond dit product ooit op 0" niet af te leiden (er wordt nergens een
     * stock-historie bijgehouden), dus de scanner houdt zelf bij wanneer een
     * product de 0-grens kruist:
     * - `automation_stock_zero_at`: wanneer dit product voor het laatst
     *   *nieuw* op 0 voorraad werd waargenomen (een nieuwe "0-episode").
     * - `automation_stock_recovered_at`: wanneer diezelfde episode voor het
     *   laatst als hersteld (stock > 0) werd waargenomen.
     * Zie StockRuleScanner voor de volledige afweging.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('dashed__products', 'automation_stock_zero_at')) {
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->timestamp('automation_stock_zero_at')
                    ->after('low_stock_alerted_at')
                    ->nullable();
            });
        }

        if (! Schema::hasColumn('dashed__products', 'automation_stock_recovered_at')) {
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->timestamp('automation_stock_recovered_at')
                    ->after('automation_stock_zero_at')
                    ->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__products', 'automation_stock_recovered_at')) {
                $table->dropColumn('automation_stock_recovered_at');
            }

            if (Schema::hasColumn('dashed__products', 'automation_stock_zero_at')) {
                $table->dropColumn('automation_stock_zero_at');
            }
        });
    }
};
