<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments') || ! Schema::hasTable('dashed__orders')) {
            return;
        }

        // Eénmalige opschoning: bestaande inschrijvingen op opvolg-mails die
        // bij een PROFORMA / RETURN / concept / cancelled order horen
        // verwijderen. Vóór v4.24.0 vuurde de fulfillment-status-listener
        // ook op concept-bewerkingen, dus die orders kregen ten onrechte
        // enrollments. Volledig deleten zodat ze nergens meer in de UI
        // verschijnen. Voor envs waar deze migratie eerder al de 'cancel'-
        // variant draaide (cancelled_reason='proforma_order') ruimen we
        // ook die rijen op zodat overal hetzelfde eindbeeld ontstaat.
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
        // Niet-omkeerbaar: we hebben de oorspronkelijke cancelled_at niet
        // bewaard. Een rollback zou alle 'proforma_order'-cancellaties weer
        // actief maken — dat willen we juist niet (de fix-listener weert ze
        // sowieso, dus ze zouden nooit een mail krijgen).
    }
};
