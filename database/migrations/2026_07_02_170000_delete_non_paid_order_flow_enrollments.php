<?php

use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

return new class extends Migration
{
    /**
     * Verwijder inschrijvingen die nooit in de opvolg-flow hadden mogen zitten:
     * proforma-/retour-facturen en niet-betaalde orders. De eerdere migratie
     * annuleerde ze enkel (cancelled_at), waardoor ze in de flow-lijst zichtbaar
     * bleven. Deze migratie haalt ze volledig weg. Wordt zo'n order later alsnog
     * betaald én afgehandeld, dan schrijft de listener 'm vanzelf opnieuw in.
     */
    public function up(): void
    {
        OrderFlowEnrollment::query()
            ->whereHas('order', function ($query) {
                $query->whereIn('invoice_id', ['PROFORMA', 'RETURN'])
                    ->orWhereNotIn('status', ['paid', 'waiting_for_confirmation', 'partially_paid']);
            })
            ->delete();
    }

    public function down(): void
    {
        // Geen rollback: verwijderde inschrijvingen worden zo nodig opnieuw
        // aangemaakt door de listener zodra een order betaald + afgehandeld is.
    }
};
