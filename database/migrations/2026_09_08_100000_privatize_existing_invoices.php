<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Jobs\PrivatizeInvoicesJob;

/**
 * Zet de bestaande facturen en pakbonnen op prive, in de wachtrij zodat de
 * deploy er niet op wacht. Zie de job voor het waarom.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        PrivatizeInvoicesJob::dispatch();
    }

    public function down(): void
    {
        // Bestanden weer publiek maken is nooit wat je wilt; niets terug te draaien.
    }
};
