<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 1) Verwijder duplicates in dashed__product_user
         */
        DB::statement("
            DELETE pu1
            FROM dashed__product_user pu1
            INNER JOIN dashed__product_user pu2
                ON pu1.product_id = pu2.product_id
               AND pu1.user_id = pu2.user_id
               AND pu1.id < pu2.id
               AND (
                    COALESCE(pu1.price, 0) <= COALESCE(pu2.price, 0)
                )
        ");

        /**
         * Extra safety pass:
         * Als er nog duplicates zijn, pak altijd de hoogste id
         */
        DB::statement("
            DELETE pu1
            FROM dashed__product_user pu1
            INNER JOIN dashed__product_user pu2
                ON pu1.product_id = pu2.product_id
               AND pu1.user_id = pu2.user_id
               AND pu1.id < pu2.id
        ");


        /**
         * dashed__product_user
         */
        Schema::table('dashed__product_user', function (Blueprint $table) {

            // UNIQUE (product_id, user_id)
            $this->addIndexIfNotExists(
                'dashed__product_user',
                'dashed__product_user_product_user_unique',
                'unique',
                ['product_id', 'user_id']
            );
        });

        /**
         * dashed__product_category_user
         */
        Schema::table('dashed__product_category_user', function (Blueprint $table) {

            // INDEX (product_category_id)
            $this->addIndexIfNotExists(
                'dashed__product_category_user',
                'dpcu_product_category_id_index',
                'index',
                ['product_category_id']
            );

            // INDEX (product_category_id, user_id)
            $this->addIndexIfNotExists(
                'dashed__product_category_user',
                'dpcu_category_user_index',
                'index',
                ['product_category_id', 'user_id']
            );
        });

        /**
         * dashed__product_filter
         */
        Schema::table('dashed__product_filter', function (Blueprint $table) {

            // INDEX (product_id)
            $this->addIndexIfNotExists(
                'dashed__product_filter',
                'dpf_product_id_index',
                'index',
                ['product_id']
            );

            // INDEX (product_filter_option_id)
            $this->addIndexIfNotExists(
                'dashed__product_filter',
                'dpf_option_id_index',
                'index',
                ['product_filter_option_id']
            );

            // INDEX (product_id, product_filter_id)
            $this->addIndexIfNotExists(
                'dashed__product_filter',
                'dpf_product_filter_index',
                'index',
                ['product_id', 'product_filter_id']
            );
        });
    }

    public function down(): void
    {
        Schema::table('dashed__product_user', function (Blueprint $table) {
            $table->dropUnique('dashed__product_user_product_user_unique');
        });

        Schema::table('dashed__product_category_user', function (Blueprint $table) {
            $table->dropIndex('dpcu_product_category_id_index');
            $table->dropIndex('dpcu_category_user_index');
        });

        Schema::table('dashed__product_filter', function (Blueprint $table) {
            $table->dropIndex('dpf_product_id_index');
            $table->dropIndex('dpf_option_id_index');
            $table->dropIndex('dpf_product_filter_index');
        });
    }

    /**
     * Helper: voeg index toe als hij nog niet bestaat
     */
    private function addIndexIfNotExists(
        string $table,
        string $indexName,
        string $type,
        array $columns
    ): void {
        $exists = DB::selectOne("
            SELECT COUNT(1) as count
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = DATABASE()
            AND table_name = ?
            AND index_name = ?
        ", [$table, $indexName]);

        if ($exists->count == 0) {
            Schema::table($table, function (Blueprint $table) use ($type, $columns, $indexName) {
                if ($type === 'unique') {
                    $table->unique($columns, $indexName);
                } else {
                    $table->index($columns, $indexName);
                }
            });
        }
    }
};
