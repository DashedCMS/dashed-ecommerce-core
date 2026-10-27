<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('dashed__discount_codes', 'giftcard_type')) {
            Schema::table('dashed__discount_codes', function (Blueprint $table) {
                $table->string('giftcard_type', 20)->default('promo');
            });
        }

        if (! Schema::hasColumn('dashed__discount_codes', 'source_order_product_id')) {
            Schema::table('dashed__discount_codes', function (Blueprint $table) {
                $table->unsignedBigInteger('source_order_product_id')->nullable()->index();
            });
        }

        foreach (['dashed__products', 'dashed__product_groups'] as $tableName) {
            foreach (['is_giftcard_product', 'is_digital'] as $column) {
                if (! Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, function (Blueprint $table) use ($column) {
                        $table->boolean($column)->default(false);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['dashed__products', 'dashed__product_groups'] as $tableName) {
            foreach (['is_giftcard_product', 'is_digital'] as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasColumn('dashed__discount_codes', 'source_order_product_id')) {
            Schema::table('dashed__discount_codes', function (Blueprint $table) {
                $table->dropIndex(['source_order_product_id']);
                $table->dropColumn('source_order_product_id');
            });
        }

        if (Schema::hasColumn('dashed__discount_codes', 'giftcard_type')) {
            Schema::table('dashed__discount_codes', function (Blueprint $table) {
                $table->dropColumn('giftcard_type');
            });
        }
    }
};
