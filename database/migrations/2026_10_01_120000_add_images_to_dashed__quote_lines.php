<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__quote_lines') || Schema::hasColumn('dashed__quote_lines', 'images')) {
            return;
        }

        Schema::table('dashed__quote_lines', function (Blueprint $table) {
            // Media-id's uit de mediabibliotheek, in de volgorde van het formulier.
            $table->json('images')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('dashed__quote_lines') && Schema::hasColumn('dashed__quote_lines', 'images')) {
            Schema::table('dashed__quote_lines', function (Blueprint $table) {
                $table->dropColumn('images');
            });
        }
    }
};
