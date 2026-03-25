<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->index('fulfillment_status', 'orders_fulfillment_status_index');
            $table->index('created_at', 'orders_created_at_index');
            $table->index('site_id', 'orders_site_id_index');
            $table->index(['status', 'fulfillment_status'], 'orders_status_fulfillment_index');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropIndex('orders_fulfillment_status_index');
            $table->dropIndex('orders_created_at_index');
            $table->dropIndex('orders_site_id_index');
            $table->dropIndex('orders_status_fulfillment_index');
        });
    }
};
