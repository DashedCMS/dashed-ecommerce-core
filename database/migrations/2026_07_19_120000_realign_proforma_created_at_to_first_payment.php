<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Terugwerkende correctie voor de "zwevende omzet". Een proforma-/concept-
     * factuur (bijv. vanuit de POS gemaild) hield zijn created_at op het moment
     * van aanmaken, ook als hij pas een maand later werd betaald. Omzet telt op
     * created_at, dus die betaling belandde in de verkeerde periode.
     *
     * Zet created_at van reeds betaalde proforma-orders gelijk aan de eerste
     * betaalde OrderPayment. Query-builder update zonder events/updated_at-bump.
     */
    public function up(): void
    {
        Order::realignProformaCreatedAtToFirstPayment();
    }

    public function down(): void
    {
        // Geen rollback: de oorspronkelijke aanmaakdatum is bewust vervangen
        // door de betaaldatum en wordt niet apart bewaard.
    }
};
