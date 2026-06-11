<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Terugwerkende kracht: verzendlabels die via de app zijn gedownload (er staat
     * een label_pdf_path) maar nog als 'in de wachtrij' (label_printed = 0) stonden,
     * alsnog uit de wachtrij halen.
     */
    public function up(): void
    {
        foreach (['dashed__order_veloyd', 'dashed__order_my_parcel'] as $table) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'label_pdf_path')
                || ! Schema::hasColumn($table, 'label_printed')) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('label_pdf_path')
                ->where('label_pdf_path', '!=', '')
                ->where(function ($q): void {
                    $q->where('label_printed', 0)->orWhereNull('label_printed');
                })
                ->update(['label_printed' => 1]);
        }
    }

    public function down(): void
    {
        // Niet terugdraaibaar.
    }
};
