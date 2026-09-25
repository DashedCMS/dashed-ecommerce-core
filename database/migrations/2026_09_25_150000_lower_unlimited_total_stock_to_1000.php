<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

/**
 * calculateStock() zette 100000 neer voor onbeperkt verkoopbare producten;
 * dat is nu Product::UNLIMITED_STOCK (1000). Bestaande rijen krijgen de nieuwe
 * waarde meteen, in plaats van pas bij de eerstvolgende keer opslaan.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('dashed__products', 'total_stock')) {
            return;
        }

        DB::table('dashed__products')
            ->where('total_stock', 100000)
            ->update(['total_stock' => 1000]);
    }

    public function down(): void
    {
    }
};
