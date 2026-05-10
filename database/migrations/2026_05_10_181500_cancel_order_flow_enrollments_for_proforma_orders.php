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

        // Eénmalige opschoning: bestaande inschrijvingen op opvolg-mails die
        // bij een PROFORMA / RETURN / concept / cancelled order horen
        // annuleren. Vóór v4.24.0 vuurde de fulfillment-status-listener ook
        // op concept-bewerkingen, dus die orders kregen ten onrechte
        // enrollments. We deleten ze niet (audit-trail blijft) maar zetten
        // ze op cancelled met reden 'proforma_order'.
        $now = now();

        DB::table('dashed__order_flow_enrollments')
            ->whereNull('cancelled_at')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('dashed__orders')
                    ->whereColumn('dashed__orders.id', 'dashed__order_flow_enrollments.order_id')
                    ->where(function ($w) {
                        $w->whereIn('dashed__orders.invoice_id', ['PROFORMA', 'RETURN'])
                          ->orWhereIn('dashed__orders.status', ['concept', 'cancelled']);
                    });
            })
            ->update([
                'cancelled_at' => $now,
                'cancelled_reason' => 'proforma_order',
                'next_mail_at' => null,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Niet-omkeerbaar: we hebben de oorspronkelijke cancelled_at niet
        // bewaard. Een rollback zou alle 'proforma_order'-cancellaties weer
        // actief maken — dat willen we juist niet (de fix-listener weert ze
        // sowieso, dus ze zouden nooit een mail krijgen).
    }
};
