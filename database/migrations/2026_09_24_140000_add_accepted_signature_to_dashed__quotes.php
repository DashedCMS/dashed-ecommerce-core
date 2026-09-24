<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__quotes') && ! Schema::hasColumn('dashed__quotes', 'accepted_signature')) {
            Schema::table('dashed__quotes', function (Blueprint $table) {
                $table->longText('accepted_signature')->nullable()->after('accepted_ip');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__quotes', 'accepted_signature')) {
            Schema::table('dashed__quotes', function (Blueprint $table) {
                $table->dropColumn('accepted_signature');
            });
        }
    }
};
