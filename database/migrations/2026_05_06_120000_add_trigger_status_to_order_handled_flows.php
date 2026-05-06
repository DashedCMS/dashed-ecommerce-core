<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_handled_flows')) {
            return;
        }

        Schema::table('dashed__order_handled_flows', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__order_handled_flows', 'trigger_status')) {
                // Default 'handled' zodat bestaande flows hun bestaande gedrag behouden.
                $table->string('trigger_status')->default('handled')->after('name');
                $table->index('trigger_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__order_handled_flows')) {
            return;
        }

        Schema::table('dashed__order_handled_flows', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__order_handled_flows', 'trigger_status')) {
                $table->dropIndex(['trigger_status']);
                $table->dropColumn('trigger_status');
            }
        });
    }
};
