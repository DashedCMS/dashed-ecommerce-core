<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->json('concept_cart_snapshot')->nullable()->after('prices_ex_vat');
            $table->string('concept_discount_code')->nullable()->after('concept_cart_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropColumn(['concept_cart_snapshot', 'concept_discount_code']);
        });
    }
};
