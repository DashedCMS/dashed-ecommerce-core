<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            // Handmatige markering "prioriteit": valt op in alle overzichten
            // (CMS + app) en is daar filterbaar.
            $table->boolean('is_priority')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropColumn('is_priority');
        });
    }
};
