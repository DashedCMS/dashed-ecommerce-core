<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Alleen de sha256 van de sleutel; de sleutel zelf wordt één keer getoond.
            $table->string('token_hash', 64)->unique();
            $table->string('token_prefix', 12);
            $table->json('abilities');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__api_clients');
    }
};
