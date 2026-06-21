<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_products')) {
            return;
        }

        Schema::table('dashed__order_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('dashed__order_products', 'added_via')) {
                // Bron waarmee de regel in het mandje is beland (bijv. 'cross_sell').
                // Null = normaal toegevoegd.
                $table->string('added_via')->nullable()->after('product_extras');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__order_products')) {
            return;
        }

        Schema::table('dashed__order_products', function (Blueprint $table): void {
            if (Schema::hasColumn('dashed__order_products', 'added_via')) {
                $table->dropColumn('added_via');
            }
        });
    }
};
