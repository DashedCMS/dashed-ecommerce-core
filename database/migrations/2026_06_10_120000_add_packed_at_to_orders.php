<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table): void {
            $table->timestamp('packed_at')->nullable()->after('fulfillment_status');
            $table->index('packed_at');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table): void {
            $table->dropIndex(['packed_at']);
            $table->dropColumn('packed_at');
        });
    }
};
