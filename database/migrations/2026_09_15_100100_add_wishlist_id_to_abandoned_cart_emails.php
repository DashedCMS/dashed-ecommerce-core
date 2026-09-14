<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
            $table->unsignedBigInteger('wishlist_id')->nullable()->after('cancelled_order_id')->index('idx_acm_wishlist');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
            $table->dropIndex('idx_acm_wishlist');
            $table->dropColumn('wishlist_id');
        });
    }
};
