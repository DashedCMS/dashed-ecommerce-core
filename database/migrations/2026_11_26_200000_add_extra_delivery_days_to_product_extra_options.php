<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Extra working days of lead time when a customer picks this option (e.g. a custom colour).
     */
    public function up(): void
    {
        if (Schema::hasColumn('dashed__product_extra_options', 'extra_delivery_days')) {
            return;
        }

        Schema::table('dashed__product_extra_options', function (Blueprint $table) {
            $table->unsignedInteger('extra_delivery_days')
                ->default(0)
                ->after('skip_stock');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__product_extra_options', 'extra_delivery_days')) {
            Schema::table('dashed__product_extra_options', function (Blueprint $table) {
                $table->dropColumn('extra_delivery_days');
            });
        }
    }
};
