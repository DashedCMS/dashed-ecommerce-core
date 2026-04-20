<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__pos_carts', function (Blueprint $table) {
            $table->boolean('prices_ex_vat')->default(false)->after('loaded_concept_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__pos_carts', function (Blueprint $table) {
            $table->dropColumn('prices_ex_vat');
        });
    }
};
