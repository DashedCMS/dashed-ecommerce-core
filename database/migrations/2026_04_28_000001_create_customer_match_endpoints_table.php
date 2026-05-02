<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('customer_match_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('username');
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->json('customer_filter')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->string('last_accessed_ip', 45)->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_match_endpoints');
    }
};
