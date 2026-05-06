<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Vervang de string-kolom (VARCHAR 255) door een text-kolom zodat
 * cart-log messages niet meer overlopen op vrije-tekst input zoals
 * een per ongeluk als kortingscode geinterpreteerde URL.
 *
 * De activitylogger truncate al voor sane uitvoer, maar deze
 * kolom-bump is een safety-net voor toekomstige logregels die
 * content via $message of via andere events bevatten.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__cart_logs')) {
            return;
        }

        Schema::table('dashed__cart_logs', function (Blueprint $table) {
            $table->text('message')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__cart_logs')) {
            return;
        }

        Schema::table('dashed__cart_logs', function (Blueprint $table) {
            $table->string('message')->nullable()->change();
        });
    }
};
