<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedCore\Security\Uploads\PrivateUploads;
use Dashed\DashedEcommerceCore\Jobs\PrivatizeProductExtraFiles;

/**
 * Bestaande bestanden bij product-extra's naar de privé opslag (dashed-core
 * PrivateUploads), via een queued job. Alleen in productie: lokaal draait
 * deze migratie vaak op een kopie van de productiedatabase met de echte
 * DigitalOcean-disk erachter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_products') || ! PrivateUploads::privatizeOnMigrate()) {
            return;
        }

        rescue(fn () => PrivatizeProductExtraFiles::dispatch(), null, true);
    }

    public function down(): void
    {
        // Bestanden gaan niet terug naar een publieke map.
    }
};
