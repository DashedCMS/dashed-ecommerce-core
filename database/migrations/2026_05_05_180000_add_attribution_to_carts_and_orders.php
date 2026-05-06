<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Voegt UTM- en attributie-kolommen toe aan dashed__carts en dashed__orders.
     * Alle kolommen zijn nullable zodat bestaande rijen niet breken.
     */
    public function up(): void
    {
        if (Schema::hasTable('dashed__carts')) {
            Schema::table('dashed__carts', function (Blueprint $table) {
                $this->addAttributionColumns($table, 'dashed__carts');
            });
        }

        if (Schema::hasTable('dashed__orders')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $this->addAttributionColumns($table, 'dashed__orders');
            });
        }
    }

    public function down(): void
    {
        foreach (['dashed__carts', 'dashed__orders'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $this->dropAttributionColumns($table, $tableName);
            });
        }
    }

    protected function addAttributionColumns(Blueprint $table, string $tableName): void
    {
        $columns = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'gclid',
            'fbclid',
            'msclkid',
        ];

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                $table->string($column, 255)->nullable();
            }
        }

        if (! Schema::hasColumn($tableName, 'landing_page')) {
            $table->string('landing_page', 2048)->nullable();
        }
        if (! Schema::hasColumn($tableName, 'landing_page_referrer')) {
            $table->string('landing_page_referrer', 2048)->nullable();
        }
        if (! Schema::hasColumn($tableName, 'attribution_first_touch_at')) {
            $table->timestamp('attribution_first_touch_at')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'attribution_last_touch_at')) {
            $table->timestamp('attribution_last_touch_at')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'attribution_extra')) {
            $table->json('attribution_extra')->nullable();
        }

        // Indexen om filtering vlot te houden.
        $sourceMediumIndex = "{$tableName}_utm_source_utm_medium_idx";
        $campaignIndex = "{$tableName}_utm_campaign_idx";

        if (! $this->indexExists($tableName, $sourceMediumIndex)) {
            $table->index(['utm_source', 'utm_medium'], $sourceMediumIndex);
        }
        if (! $this->indexExists($tableName, $campaignIndex)) {
            $table->index('utm_campaign', $campaignIndex);
        }
    }

    protected function dropAttributionColumns(Blueprint $table, string $tableName): void
    {
        $sourceMediumIndex = "{$tableName}_utm_source_utm_medium_idx";
        $campaignIndex = "{$tableName}_utm_campaign_idx";

        if ($this->indexExists($tableName, $sourceMediumIndex)) {
            $table->dropIndex($sourceMediumIndex);
        }
        if ($this->indexExists($tableName, $campaignIndex)) {
            $table->dropIndex($campaignIndex);
        }

        $columns = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'gclid',
            'fbclid',
            'msclkid',
            'landing_page',
            'landing_page_referrer',
            'attribution_first_touch_at',
            'attribution_last_touch_at',
            'attribution_extra',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn($tableName, $column)) {
                $table->dropColumn($column);
            }
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaManager = method_exists($connection, 'getDoctrineSchemaManager')
                ? $connection->getDoctrineSchemaManager()
                : null;

            if ($schemaManager) {
                return array_key_exists(
                    strtolower($indexName),
                    array_change_key_case($schemaManager->listTableIndexes($table), CASE_LOWER)
                );
            }
        } catch (\Throwable $e) {
            // Fallback hieronder.
        }

        // Fallback voor nieuwere Laravel-versies / drivers zonder DBAL.
        $indexes = collect(Schema::getIndexes($table) ?? []);

        return $indexes->contains(fn ($index) => strcasecmp($index['name'] ?? '', $indexName) === 0);
    }
};
