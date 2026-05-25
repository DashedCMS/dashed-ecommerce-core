<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__printers', function (Blueprint $table) {
            $table->text('plain_token')->nullable()->after('last_ping_at');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__printers', function (Blueprint $table) {
            $table->dropColumn('plain_token');
        });
    }
};
