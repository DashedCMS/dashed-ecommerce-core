<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('dashed__printers', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('name', 100);
            $table->string('location', 100)->nullable();
            $table->string('type', 30);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('last_ping_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__printers');
    }
};
