<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['dashed__products', 'dashed__product_groups'] as $table) {
            if (! Schema::hasColumn($table, 'gs1_excluded')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->boolean('gs1_excluded')->default(false);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['dashed__products', 'dashed__product_groups'] as $table) {
            if (Schema::hasColumn($table, 'gs1_excluded')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('gs1_excluded');
                });
            }
        }
    }
};
