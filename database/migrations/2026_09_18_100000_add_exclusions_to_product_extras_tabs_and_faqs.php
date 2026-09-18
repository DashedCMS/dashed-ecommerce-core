<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['dashed__product_extras', 'dashed__product_tabs', 'dashed__product_faqs'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'excluded_product_ids')) {
                    $table->json('excluded_product_ids')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'excluded_product_group_ids')) {
                    $table->json('excluded_product_group_ids')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};
