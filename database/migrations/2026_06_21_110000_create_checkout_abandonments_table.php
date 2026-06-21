<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__checkout_abandonments')) {
            return;
        }

        Schema::create('dashed__checkout_abandonments', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id')->nullable();
            $table->unsignedBigInteger('cart_id')->nullable()->index();
            $table->string('email')->nullable();
            // Reden waarom de checkout strandde vóór order-creatie (machinekey).
            $table->string('reason')->index();
            $table->json('context')->nullable();
            $table->decimal('cart_total', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['reason', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__checkout_abandonments');
    }
};
