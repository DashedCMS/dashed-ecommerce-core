<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
            $table->string('trigger_type', 32)->default('cart_with_email')->after('cart_id');
            $table->unsignedBigInteger('cancelled_order_id')->nullable()->after('trigger_type');
            $table->string('cancelled_reason', 64)->nullable()->after('cancelled_at');

            $table->index('trigger_type', 'idx_acm_trigger_type');
            $table->foreign('cancelled_order_id', 'fk_acm_cancelled_order')
                ->references('id')
                ->on('dashed__orders')
                ->nullOnDelete();
        });

        Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
            $table->unsignedBigInteger('cart_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
            $table->dropForeign('fk_acm_cancelled_order');
            $table->dropIndex('idx_acm_trigger_type');
            $table->dropColumn(['trigger_type', 'cancelled_order_id', 'cancelled_reason']);
        });
    }
};
