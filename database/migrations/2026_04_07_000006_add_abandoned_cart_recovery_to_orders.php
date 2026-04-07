<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashed__orders') && ! Schema::hasColumn('dashed__orders', 'abandoned_cart_recovery')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->boolean('abandoned_cart_recovery')->default(false)->after('ga_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__orders', 'abandoned_cart_recovery')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->dropColumn('abandoned_cart_recovery');
            });
        }
    }
};
