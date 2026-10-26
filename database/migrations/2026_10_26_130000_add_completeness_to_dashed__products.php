<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Jobs\StoreProductCompletenessJob;

return new class () extends Migration {
    public function up(): void
    {
        // Volledigheid in procenten, voor de kolom en het filter "onder 80%"
        // in de productlijst. Afgeleid uit ProductCompleteness; leeg tot de
        // eerste berekening, die hieronder in de wachtrij gaat.
        if (! Schema::hasColumn('dashed__products', 'completeness')) {
            Schema::table('dashed__products', function (Blueprint $table) {
                $table->unsignedTinyInteger('completeness')->nullable()->index();
            });
        }

        StoreProductCompletenessJob::dispatch();
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__products', 'completeness')) {
            Schema::table('dashed__products', fn (Blueprint $table) => $table->dropColumn('completeness'));
        }
    }
};
