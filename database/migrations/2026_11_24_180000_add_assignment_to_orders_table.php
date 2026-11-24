<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Toewijzing: assigned_to_user_id bestaat al (2026_11_13_150000); hier
        // alleen het moment waarop iemand de bestelling toegewezen kreeg.
        if (! Schema::hasColumn('dashed__orders', 'assigned_at')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->timestamp('assigned_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__orders', 'assigned_at')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->dropColumn('assigned_at');
            });
        }
    }
};
