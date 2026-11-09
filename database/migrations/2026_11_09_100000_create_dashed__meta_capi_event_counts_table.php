<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__meta_capi_event_counts')) {
            return;
        }

        // Dagtellers van de browser-events (ViewContent, AddToCart,
        // InitiateCheckout): één rij per site, dag en eventsoort in plaats van
        // een logregel per event.
        Schema::create('dashed__meta_capi_event_counts', function (Blueprint $table) {
            $table->id();
            $table->string('site_id');
            $table->date('date');
            $table->string('event_name', 60);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamps();
            $table->unique(['site_id', 'date', 'event_name'], 'meta_capi_event_counts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__meta_capi_event_counts');
    }
};
