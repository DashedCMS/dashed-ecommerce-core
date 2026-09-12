<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_returns')) {
            return;
        }

        Schema::table('dashed__order_returns', function (Blueprint $table) {
            // Bewust geen databaseconstraint: SQLite (de testsuite) kan geen
            // foreign key toevoegen aan een bestaande tabel, en orders worden
            // toch zacht verwijderd. De relatie zit in OrderReturn::creditOrder().
            if (! Schema::hasColumn('dashed__order_returns', 'credit_order_id')) {
                $table->unsignedBigInteger('credit_order_id')->nullable()->index();
            }
            if (! Schema::hasColumn('dashed__order_returns', 'processed_at')) {
                $table->dateTime('processed_at')->nullable();
            }
            if (! Schema::hasColumn('dashed__order_returns', 'closed_reason')) {
                $table->text('closed_reason')->nullable();
            }
            if (! Schema::hasColumn('dashed__order_returns', 'closed_at')) {
                $table->dateTime('closed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__order_returns', function (Blueprint $table) {
            $table->dropColumn(['credit_order_id', 'processed_at', 'closed_reason', 'closed_at']);
        });
    }
};
