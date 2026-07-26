<?php
declare(strict_types=1);
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__automation_rules')) {
            return;
        }
        Schema::table('dashed__automation_rules', function (Blueprint $table) {
            $table->json('schedule')->nullable()->after('actions');
        });
    }
    public function down(): void
    {
        if (! Schema::hasColumn('dashed__automation_rules', 'schedule')) {
            return;
        }
        Schema::table('dashed__automation_rules', function (Blueprint $table) {
            $table->dropColumn('schedule');
        });
    }
};
