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
            if (! Schema::hasColumn('dashed__order_handled_flows', 'order_origins')) {
                $table->json('order_origins')->nullable()->after('trigger_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__order_handled_flows')) {
            return;
        }

        Schema::table('dashed__order_handled_flows', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__order_handled_flows', 'order_origins')) {
                $table->dropColumn('order_origins');
            }
        });
    }
};
