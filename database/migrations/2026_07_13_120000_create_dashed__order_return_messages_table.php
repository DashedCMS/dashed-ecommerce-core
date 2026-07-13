<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__order_return_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained('dashed__order_returns')->cascadeOnDelete();
            $table->string('sender');
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_return_messages');
    }
};
