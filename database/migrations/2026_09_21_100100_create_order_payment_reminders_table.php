<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__order_payment_reminders')) {
            return;
        }

        Schema::create('dashed__order_payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('dashed__orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('stage');
            $table->timestamp('sent_at');
            $table->string('email');
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_payment_reminders');
    }
};
