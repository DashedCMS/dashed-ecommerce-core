<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_return_lines')) {
            return;
        }

        Schema::table('dashed__order_return_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__order_return_lines', 'processed_quantity')) {
                $table->unsignedInteger('processed_quantity')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__order_return_lines', function (Blueprint $table) {
            $table->dropColumn('processed_quantity');
        });
    }
};
