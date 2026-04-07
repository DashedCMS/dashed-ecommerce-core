<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashed__abandoned_cart_flows') && ! Schema::hasColumn('dashed__abandoned_cart_flows', 'discount_prefix')) {
            Schema::table('dashed__abandoned_cart_flows', function (Blueprint $table) {
                $table->string('discount_prefix', 20)->default('TERUG')->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__abandoned_cart_flows', 'discount_prefix')) {
            Schema::table('dashed__abandoned_cart_flows', function (Blueprint $table) {
                $table->dropColumn('discount_prefix');
            });
        }
    }
};
