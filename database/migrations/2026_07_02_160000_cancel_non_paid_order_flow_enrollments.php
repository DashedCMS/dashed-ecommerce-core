<?php

use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

return new class extends Migration
{
    /**
     * Ruim bestaande inschrijvingen op die nooit in de opvolg-flow hadden mogen
     * zitten: proforma-/retour-facturen en niet-betaalde orders. Die zijn er
     * eerder ingeglipt via de bulk-backfill (die enkel op fulfillment_status
     * filterde). Annuleer ze zodat ze geen mail meer krijgen.
     */
    public function up(): void
    {
        OrderFlowEnrollment::query()
            ->whereNull('cancelled_at')
            ->whereHas('order', function ($query) {
                $query->whereIn('invoice_id', ['PROFORMA', 'RETURN'])
                    ->orWhereNotIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid']);
            })
            ->update([
                'cancelled_at' => now(),
                'cancelled_reason' => 'order_not_paid_or_proforma',
                'next_mail_at' => null,
            ]);
    }

    public function down(): void
    {
        // Bewust een no-op: her-inschrijven zou proforma/onbetaalde orders
        // opnieuw in de flow zetten.
    }
};
