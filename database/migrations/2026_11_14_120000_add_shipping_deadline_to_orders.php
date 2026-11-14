<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__orders')) {
            return;
        }

        if (! Schema::hasColumn('dashed__orders', 'ship_by_date')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->date('ship_by_date')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('dashed__orders', 'deliver_by_date')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->date('deliver_by_date')->nullable();
            });
        }

        if (! Schema::hasColumn('dashed__orders', 'deadline_source')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->string('deadline_source', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['ship_by_date', 'deliver_by_date', 'deadline_source'] as $column) {
            if (Schema::hasColumn('dashed__orders', $column)) {
                Schema::table('dashed__orders', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
