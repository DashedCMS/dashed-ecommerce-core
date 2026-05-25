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
            $table->json('cups_printers')->nullable()->after('plain_token');
            $table->string('cups_name')->nullable()->after('cups_printers');
            $table->string('hostname')->nullable()->after('cups_name');
            $table->string('pairing_code', 12)->nullable()->unique()->after('hostname');
            $table->timestamp('pairing_expires_at')->nullable()->after('pairing_code');
            $table->timestamp('paired_at')->nullable()->after('pairing_expires_at');

            $table->index('pairing_code');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__printers', function (Blueprint $table) {
            $table->dropIndex(['pairing_code']);
            $table->dropColumn([
                'cups_printers',
                'cups_name',
                'hostname',
                'pairing_code',
                'pairing_expires_at',
                'paired_at',
            ]);
        });
    }
};
