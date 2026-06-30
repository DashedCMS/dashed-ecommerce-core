<?php
// database/migrations/2026_06_29_120000_add_proforma_fields_to_orders.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->boolean('is_proforma')->default(false)->after('order_origin');
            $table->boolean('proforma_allow_shipping')->default(false)->after('is_proforma');
            $table->timestamp('proforma_sent_at')->nullable()->after('proforma_allow_shipping');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropColumn(['is_proforma', 'proforma_allow_shipping', 'proforma_sent_at']);
        });
    }
};
