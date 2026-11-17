<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

/**
 * Labels met een PDF zijn gelukt: een oude fout erop (bv. een tijdelijke 401)
 * telt niet meer als labelprobleem. Wis die fout, alleen via een query zodat
 * er geen model-events afgaan. Idempotent.
 */
return new class () extends Migration {
    public function up(): void
    {
        foreach (['dashed__order_veloyd', 'dashed__order_my_parcel'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'error') || ! Schema::hasColumn($table, 'label_pdf_path')) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('error')
                ->whereNotNull('label_pdf_path')
                ->where('label_pdf_path', '!=', '')
                ->update(['error' => null]);
        }
    }

    public function down(): void
    {
        // De gewiste foutmeldingen komen niet terug.
    }
};
