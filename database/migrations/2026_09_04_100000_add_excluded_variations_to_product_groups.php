<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('dashed__product_groups', 'excluded_variations')) {
            Schema::table('dashed__product_groups', function (Blueprint $table) {
                $table->json('excluded_variations')
                    ->nullable()
                    ->after('missing_variations');
            });
        }
    }

    public function down(): void
    {
        //
    }
};
