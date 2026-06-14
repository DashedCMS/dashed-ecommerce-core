<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Idempotentie-logboek voor de mobiele app: client-gegenereerde operatie-ids
 * (op_id) van offline ingevoerde acties (voorraad inboeken/tellen, order
 * inpakken). Komt een actie na een sync twee keer binnen, dan herkennen we het
 * op_id en passen we de mutatie NIET nog eens toe (voorkomt dubbel inboeken),
 * maar geven we het eerder bewaarde resultaat terug.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashed__processed_operations')) {
            return;
        }

        Schema::create('dashed__processed_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('op_id')->unique();
            $table->json('result_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__processed_operations');
    }
};
