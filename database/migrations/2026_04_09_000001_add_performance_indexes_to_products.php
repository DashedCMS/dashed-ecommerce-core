<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            if (! $this->hasIndex('dashed__products', 'dashed__products_public_index')) {
                $table->index('public');
            }
            if (! $this->hasIndex('dashed__products', 'dashed__products_order_index')) {
                $table->index('order');
            }
            if (! $this->hasIndex('dashed__products', 'dashed__products_product_group_id_index')) {
                $table->index('product_group_id');
            }
            if (! $this->hasIndex('dashed__products', 'dashed__products_indexable_index')) {
                $table->index('indexable');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__products', function (Blueprint $table) {
            $table->dropIndex(['public']);
            $table->dropIndex(['order']);
            $table->dropIndex(['product_group_id']);
            $table->dropIndex(['indexable']);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (\DB::connection()->getDriverName() === 'sqlite') {
            $indexes = collect(\DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=?", [$table]))
                ->pluck('name');
        } else {
            $indexes = collect(\DB::select("SHOW INDEX FROM {$table}"))
                ->pluck('Key_name')
                ->unique();
        }

        return $indexes->contains($indexName);
    }
};
