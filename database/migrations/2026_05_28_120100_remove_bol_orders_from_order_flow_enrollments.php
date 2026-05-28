<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments') || ! Schema::hasTable('dashed__orders')) {
            return;
        }

        // Bol.com-orders horen niet in marketing-/opvolg-flows. Historische
        // enrollments die voor de policy-fix zijn aangemaakt worden hier
        // verwijderd — niet alleen cancelled, om alle resterende sporen
        // (next_mail_at e.d.) gelijk op te ruimen.
        DB::table('dashed__order_flow_enrollments')
            ->whereIn('order_id', function ($q) {
                $q->select('id')
                    ->from('dashed__orders')
                    ->where('order_origin', 'Bol');
            })
            ->delete();
    }

    public function down(): void
    {
        // Niet omkeerbaar: verwijderde enrollments zijn niet reconstrueerbaar
        // zonder verloren context, en herstel zou alsnog tegen het Bol-beleid
        // ingaan.
    }
};
