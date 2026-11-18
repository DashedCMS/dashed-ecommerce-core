<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__order_add_on_offers')) {
            return;
        }

        // Het bijbestel-aanbod per order: wanneer getoond, gemaild en geklikt.
        Schema::create('dashed__order_add_on_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('email')->nullable()->index();
            $table->timestamp('shown_at')->nullable();
            $table->timestamp('mail_sent_at')->nullable()->index();
            $table->string('mail_skipped')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_add_on_offers');
    }
};
