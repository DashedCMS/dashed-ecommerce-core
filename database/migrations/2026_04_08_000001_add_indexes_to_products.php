<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            if (! $this->hasIndex('dashed__products', 'dashed__products_sku_index')) {
                $table->index('sku');
            }
            if (! $this->hasIndex('dashed__products', 'dashed__products_ean_index')) {
                $table->index('ean');
            }
            if (! $this->hasIndex('dashed__products', 'dashed__products_price_index')) {
                $table->index('price');
            }
            if (! $this->hasIndex('dashed__products', 'dashed__products_total_purchases_index')) {
                $table->index('total_purchases');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->dropIndex(['sku']);
            $table->dropIndex(['ean']);
            $table->dropIndex(['price']);
            $table->dropIndex(['total_purchases']);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = collect(\DB::select("SHOW INDEX FROM {$table}"))
            ->pluck('Key_name')
            ->unique();

        return $indexes->contains($indexName);
    }
};
