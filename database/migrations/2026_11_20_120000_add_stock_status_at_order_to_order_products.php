<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Voorraadstatus van de regel op het moment van bestellen:
     * 'in_stock'  = het volledige bestelde aantal was direct leverbaar,
     * 'out_of_stock' = (deels) backorder/pre-order, niet (volledig) leverbaar,
     * null = onbekend of niet van toepassing (product zonder voorraadbeheer,
     *        product verwijderd, of nog niet bepaald/ge-backfilld).
     */
    public function up(): void
    {
        Schema::table('dashed__order_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('dashed__order_products', 'stock_status_at_order')) {
                $table->string('stock_status_at_order')->nullable()->after('is_pre_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__order_products', function (Blueprint $table): void {
            if (Schema::hasColumn('dashed__order_products', 'stock_status_at_order')) {
                $table->dropColumn('stock_status_at_order');
            }
        });
    }
};
