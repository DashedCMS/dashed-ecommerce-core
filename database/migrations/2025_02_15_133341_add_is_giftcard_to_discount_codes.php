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
        Schema::table('dashed__discount_codes', function (Blueprint $table) {
            $table->boolean('is_giftcard')
                ->default(false);
            $table->decimal('initial_amount', 10, 2)
                ->default(0);
        });

        Schema::create('dashed__discount_code_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('discount_code_id')
                ->constrained('dashed__discount_codes')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('dashed__orders')
                ->cascadeOnDelete();
            $table->decimal('old_amount', 10, 2)
                ->nullable();
            $table->decimal('new_amount', 10, 2)
                ->nullable();
            $table->string('tag')
                ->nullable();
            $table->text('description')
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
