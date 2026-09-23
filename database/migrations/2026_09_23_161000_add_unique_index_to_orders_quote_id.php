<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    private const INDEX = 'dashed__orders_quote_id_unique';

    public function up(): void
    {
        if (! Schema::hasTable('dashed__orders') || ! Schema::hasColumn('dashed__orders', 'quote_id')) {
            return;
        }

        if ($this->hasIndex('dashed__orders', self::INDEX)) {
            return;
        }

        // Backstop tegen een dubbele order op dezelfde offerte. De applicatielaag
        // (QuoteAcceptance::accept()) voorkomt dit al met een lockForUpdate() en
        // een vlag die alleen de aanroep die de statusovergang zelf deed laat
        // doorlopen, maar er blijft een klein venster tussen het committen van
        // die overgang en het wegschrijven van order_id waarin twee aanroepen
        // allebei "geaccepteerd zonder order" kunnen aantreffen. Deze index vangt
        // dat resterende venster af. Meerdere NULLs blijven toegestaan onder een
        // unieke index, in zowel MySQL als SQLite, dus elke bestaande order zonder
        // offerte blijft geldig.
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->unique('quote_id', self::INDEX);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('dashed__orders') && $this->hasIndex('dashed__orders', self::INDEX)) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $indexes = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=?", [$table]))
                ->pluck('name');
        } else {
            $indexes = collect(DB::select("SHOW INDEX FROM {$table}"))
                ->pluck('Key_name')
                ->unique();
        }

        return $indexes->contains($indexName);
    }
};
