<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashed__pos_carts') && ! Schema::hasColumn('dashed__pos_carts', 'applied_gift_cards')) {
            Schema::table('dashed__pos_carts', function (Blueprint $table) {
                $table->json('applied_gift_cards')->nullable()->after('discount_code');
            });
        }

        if (Schema::hasTable('dashed__orders') && ! Schema::hasColumn('dashed__orders', 'applied_gift_cards')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->json('applied_gift_cards')->nullable()->after('discount_code_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dashed__pos_carts') && Schema::hasColumn('dashed__pos_carts', 'applied_gift_cards')) {
            Schema::table('dashed__pos_carts', function (Blueprint $table) {
                $table->dropColumn('applied_gift_cards');
            });
        }

        if (Schema::hasTable('dashed__orders') && Schema::hasColumn('dashed__orders', 'applied_gift_cards')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->dropColumn('applied_gift_cards');
            });
        }
    }
};
