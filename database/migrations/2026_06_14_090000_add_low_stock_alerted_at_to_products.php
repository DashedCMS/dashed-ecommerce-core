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
        if (! Schema::hasColumn('dashed__products', 'low_stock_alerted_at')) {
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->timestamp('low_stock_alerted_at')
                    ->after('low_stock_notification_limit')
                    ->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('dashed__products', 'low_stock_alerted_at')) {
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->dropColumn('low_stock_alerted_at');
            });
        }
    }
};
