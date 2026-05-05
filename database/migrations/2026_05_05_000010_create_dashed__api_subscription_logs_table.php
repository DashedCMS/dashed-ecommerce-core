<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__api_subscription_logs')) {
            Schema::create('dashed__api_subscription_logs', function (Blueprint $table) {
                $table->id();
                $table->string('email');
                $table->string('api_class');
                $table->string('source')->default('order');
                $table->string('status')->default('success');
                $table->text('error')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->index('email');
                $table->index('api_class');
                $table->index(['email', 'api_class'], 'dashed_api_sub_logs_email_class_idx');
                $table->index('source');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__api_subscription_logs');
    }
};
