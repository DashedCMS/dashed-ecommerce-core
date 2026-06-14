<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Kassasessie (dagafsluiting / kasstaat — Z-rapport): per medewerker + site
 * één open sessie per dag. Bij openen leggen we de startkas vast, bij afsluiten
 * de getelde kas, de verwachte kas, het verschil en een snapshot van de
 * omzet per betaalmethode.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashed__pos_register_sessions')) {
            return;
        }

        Schema::create('dashed__pos_register_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamp('opened_at');
            $table->decimal('opening_float', 10, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->json('totals')->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__pos_register_sessions');
    }
};
