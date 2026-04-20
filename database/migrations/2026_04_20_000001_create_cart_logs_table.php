<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__cart_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('dashed__carts')->cascadeOnDelete();
            $table->string('event', 100);
            $table->string('message')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['cart_id', 'event']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__cart_logs');
    }
};
