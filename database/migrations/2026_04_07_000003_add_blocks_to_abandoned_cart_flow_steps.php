<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__abandoned_cart_flow_steps') && ! Schema::hasColumn('dashed__abandoned_cart_flow_steps', 'blocks')) {
            Schema::table('dashed__abandoned_cart_flow_steps', function (Blueprint $table) {
                $table->json('blocks')->nullable()->after('intro_text');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__abandoned_cart_flow_steps', 'blocks')) {
            Schema::table('dashed__abandoned_cart_flow_steps', function (Blueprint $table) {
                $table->dropColumn('blocks');
            });
        }
    }
};
