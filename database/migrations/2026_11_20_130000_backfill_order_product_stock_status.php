<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Commands\BackfillOrderProductStockStatus;

return new class () extends Migration {
    /**
     * Vult `stock_status_at_order` eenmalig bij deploy voor bestaande orders,
     * zodat niemand de artisan-command handmatig hoeft te draaien. Best-effort
     * via de reeds opgeslagen `is_pre_order`-vlag (er is geen voorraadhistorie);
     * onbekende rijen blijven NULL. Idempotent: raakt alleen rijen zonder waarde.
     * De command `dashed:backfill-order-product-stock-status` blijft bestaan voor
     * een dry-run of een herberekening (`--all`).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('dashed__order_products', 'stock_status_at_order')) {
            return;
        }

        BackfillOrderProductStockStatus::backfill();
    }

    public function down(): void
    {
        // Data-migratie: niet terug te draaien (de afgeleide status gaat verloren
        // bij een down zonder dat we weten welke rijen eerst NULL waren).
    }
};
