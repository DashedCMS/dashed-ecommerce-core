<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Terugwerkende correctie: omzet telt op created_at, en een order die op
     * een latere dag betaald werd dan hij aangemaakt was (overboeking,
     * betaallink, alsnog betaald na annulering) telde in de periode van
     * aanmaken. Zet created_at op het moment dat de order omzet ging tellen,
     * volgens het orderlogboek. Zie Order::realignLatePaidCreatedAtToPaymentDate().
     */
    public function up(): void
    {
        Order::realignLatePaidCreatedAtToPaymentDate();
    }

    public function down(): void
    {
        // Geen rollback: de oorspronkelijke aanmaakdatum is bewust vervangen
        // door de betaaldatum en wordt niet apart bewaard.
    }
};
