<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            // Bewust zonder foreign key constraint: de testsuite draait op
            // SQLite en een constraint toevoegen aan een bestaande tabel
            // dwingt daar een tabel-rebuild af.
            $table->unsignedBigInteger('replaced_by_order_id')->nullable();
            $table->index('replaced_by_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropIndex(['replaced_by_order_id']);
            $table->dropColumn('replaced_by_order_id');
        });
    }
};
