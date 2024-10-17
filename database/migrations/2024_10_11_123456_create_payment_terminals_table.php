<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashed__pin_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('site_id');
            $table->string('psp');
            $table->string('pin_terminal_id');
            $table->json('name')
                ->nullable();
            $table->integer('active')
                ->default(1);
            $table->json('attributes')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('dashed__payment_methods', function (Blueprint $table) {
            $table->foreignId('pin_terminal_id')
                ->after('is_cash_payment')
                ->nullable()
                ->constrained('dashed__pin_terminals')
                ->cascadeOnDelete();
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
