<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__meta_capi_events')) {
            return;
        }

        Schema::create('dashed__meta_capi_events', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->foreignId('order_id')->nullable()->constrained('dashed__orders')->nullOnDelete();
            $table->string('event_name');
            $table->string('event_id')->unique();
            $table->json('payload')->nullable();
            $table->string('status')->default('pending')->index();
            $table->json('response')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__meta_capi_events');
    }
};
