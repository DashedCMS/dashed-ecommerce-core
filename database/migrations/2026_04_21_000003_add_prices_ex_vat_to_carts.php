<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__carts', function (Blueprint $table) {
            $table->boolean('prices_ex_vat')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('dashed__carts', function (Blueprint $table) {
            $table->dropColumn('prices_ex_vat');
        });
    }
};
