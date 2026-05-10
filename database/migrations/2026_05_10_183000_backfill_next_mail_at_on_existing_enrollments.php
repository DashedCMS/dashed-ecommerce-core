<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

/**
 * Volgt op `2026_05_10_180000_add_next_mail_at_to_order_flow_enrollments`.
 * Die migratie kreeg in v4.24.2 inline-backfill — maar installaties die
 * v4.24.0/v4.24.1 al gedraaid hadden (waar 180000 alleen het schema
 * toevoegde) staan nu in de migrations-tabel: de geüpdatete content is
 * daar dead code. Deze losse migratie dwingt de backfill alsnog af.
 *
 * Op fresh envs (die 180000 in v4.24.2+ draaien) is dit een no-op omdat
 * `next_mail_at` daar al gevuld is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments')
            || ! Schema::hasColumn('dashed__order_flow_enrollments', 'next_mail_at')) {
            return;
        }

        OrderFlowEnrollment::query()
            ->whereNull('next_mail_at')
            ->whereNull('cancelled_at')
            ->with('flow.steps')
            ->chunkById(500, function ($enrollments) {
                foreach ($enrollments as $enrollment) {
                    if (! $enrollment->flow) {
                        continue;
                    }
                    try {
                        $enrollment->recomputeNextMailAt();
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });
    }

    public function down(): void
    {
        // Niet-omkeerbaar: er is geen sentinel om "was eerder NULL" te
        // herkennen. Down is een no-op.
    }
};
