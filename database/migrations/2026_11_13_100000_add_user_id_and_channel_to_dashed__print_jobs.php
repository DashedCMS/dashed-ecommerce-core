<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__print_jobs')) {
            return;
        }

        // Wie een printopdracht klaarzette en via welk kanaal (cms, app,
        // automatisch, kassa), voor het orderlogboek.
        Schema::table('dashed__print_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__print_jobs', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('order_id');
            }
            if (! Schema::hasColumn('dashed__print_jobs', 'channel')) {
                $table->string('channel', 20)->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__print_jobs')) {
            return;
        }

        Schema::table('dashed__print_jobs', function (Blueprint $table) {
            foreach (['channel', 'user_id'] as $column) {
                if (Schema::hasColumn('dashed__print_jobs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
