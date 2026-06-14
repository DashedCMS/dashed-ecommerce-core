<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__order_products', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__order_products', 'returned_quantity')) {
                $table->unsignedInteger('returned_quantity')->default(0)->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__order_products', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__order_products', 'returned_quantity')) {
                $table->dropColumn('returned_quantity');
            }
        });
    }
};
