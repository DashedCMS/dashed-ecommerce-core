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

        // Bewust geen databaseconstraint: SQLite (de testsuite) kan geen foreign
        // key toevoegen aan een bestaande tabel. De relatie zit in Order::quote().
        if (! Schema::hasColumn('dashed__orders', 'quote_id')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->unsignedBigInteger('quote_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__orders', 'quote_id')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->dropColumn('quote_id');
            });
        }
    }
};
