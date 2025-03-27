<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashed__order_track_and_traces', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('dashed__orders')
                ->cascadeOnDelete();
            $table->string('supplier');
            $table->string('delivery_company');
            $table->string('code')
            ->nullable();
            $table->string('url')
            ->nullable();
            $table->date('expected_delivery_date')
            ->nullable();
            $table->string('status')
            ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
