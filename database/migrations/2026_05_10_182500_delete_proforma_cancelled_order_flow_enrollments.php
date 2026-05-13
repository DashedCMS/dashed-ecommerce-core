<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

/**
 * Volgt op `2026_05_10_181500_cancel_order_flow_enrollments_for_proforma_orders`.
 * In de eerste iteratie van die migratie (v4.24.3) werden proforma/concept-
 * enrollments alleen geannuleerd. We willen ze nu volledig verwijderen, ook
 * op installaties waar de eerste variant al gedraaid heeft. Op fresh envs
 * (waar 181500 al direct deletet) is dit een no-op.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments')) {
            return;
        }

        DB::table('dashed__order_flow_enrollments')
            ->where(function ($w) {
                $w->where('cancelled_reason', 'proforma_order')
                    ->orWhereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('dashed__orders')
                            ->whereColumn('dashed__orders.id', 'dashed__order_flow_enrollments.order_id')
                            ->where(function ($inner) {
                                $inner->whereIn('dashed__orders.invoice_id', ['PROFORMA', 'RETURN'])
                                    ->orWhereIn('dashed__orders.status', ['concept', 'cancelled']);
                            });
                    });
            })
            ->delete();
    }

    public function down(): void
    {
        // Niet-omkeerbaar: rijen zijn weg. Bewust geen restore.
    }
};
