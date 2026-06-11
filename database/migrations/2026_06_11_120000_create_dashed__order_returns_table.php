<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__order_returns')) {
            return;
        }

        Schema::create('dashed__order_returns', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->nullable()->index();
            $table->foreignId('order_id')->constrained('dashed__orders')->cascadeOnDelete();
            $table->string('hash', 32)->unique();
            $table->string('status')->default('requested')->index();
            $table->string('email');
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__order_returns');
    }
};
