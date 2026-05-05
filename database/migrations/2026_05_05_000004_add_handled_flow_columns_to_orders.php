<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__orders')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                if (! Schema::hasColumn('dashed__orders', 'handled_flow_started_at')) {
                    $table->timestamp('handled_flow_started_at')->nullable();
                }
                if (! Schema::hasColumn('dashed__orders', 'handled_flow_cancelled_at')) {
                    $table->timestamp('handled_flow_cancelled_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dashed__orders')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                if (Schema::hasColumn('dashed__orders', 'handled_flow_started_at')) {
                    $table->dropColumn('handled_flow_started_at');
                }
                if (Schema::hasColumn('dashed__orders', 'handled_flow_cancelled_at')) {
                    $table->dropColumn('handled_flow_cancelled_at');
                }
            });
        }
    }
};
