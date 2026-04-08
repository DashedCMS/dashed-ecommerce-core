<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__abandoned_cart_emails') && ! Schema::hasColumn('dashed__abandoned_cart_emails', 'send_at')) {
            Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
                $table->timestamp('send_at')->nullable()->after('email_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__abandoned_cart_emails', 'send_at')) {
            Schema::table('dashed__abandoned_cart_emails', function (Blueprint $table) {
                $table->dropColumn('send_at');
            });
        }
    }
};
