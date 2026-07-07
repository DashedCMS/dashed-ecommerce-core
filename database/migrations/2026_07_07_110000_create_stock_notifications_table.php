<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__stock_notifications')) {
            return;
        }
        Schema::create('dashed__stock_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('email');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__stock_notifications');
    }
};
