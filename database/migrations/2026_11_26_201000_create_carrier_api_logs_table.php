<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('carrier_api_logs')) {
            return;
        }

        Schema::create('carrier_api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('carrier', 50)->index();
            $table->string('action', 80);
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('method', 10);
            $table->text('url');
            $table->longText('request_body')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable()->index();
            $table->longText('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_api_logs');
    }
};
