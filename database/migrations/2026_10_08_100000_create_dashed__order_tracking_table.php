<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__order_tracking')) {
            return;
        }

        Schema::create('dashed__order_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('dashed__orders')->cascadeOnDelete();
            $table->string('meta_fbp')->nullable();
            // Een fbclid kan ruim over de 255 tekens gaan.
            $table->string('meta_fbc', 1000)->nullable();
            $table->text('client_user_agent')->nullable();
            $table->string('event_source_url', 2048)->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_tracking');
    }
};
