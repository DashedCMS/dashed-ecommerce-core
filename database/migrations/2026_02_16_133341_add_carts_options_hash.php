<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('dashed__cart_items', function (Blueprint $table) {
            $table->char('options_hash', 64)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
