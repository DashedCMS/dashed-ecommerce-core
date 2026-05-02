<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__abandoned_cart_flows', function (Blueprint $table) {
            $table->json('triggers')->nullable()->after('discount_prefix');
        });

        DB::table('dashed__abandoned_cart_flows')
            ->whereNull('triggers')
            ->update(['triggers' => json_encode(['cart_with_email'])]);
    }

    public function down(): void
    {
        Schema::table('dashed__abandoned_cart_flows', function (Blueprint $table) {
            $table->dropColumn('triggers');
        });
    }
};
