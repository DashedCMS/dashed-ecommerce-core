<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__abandoned_cart_flows', function (Blueprint $table) {
            $table->unsignedSmallInteger('skip_if_paid_within_days')->nullable()->default(30)->after('discount_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__abandoned_cart_flows', function (Blueprint $table) {
            $table->dropColumn('skip_if_paid_within_days');
        });
    }
};
