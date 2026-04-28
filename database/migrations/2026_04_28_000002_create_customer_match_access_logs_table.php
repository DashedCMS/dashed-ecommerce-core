<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('customer_match_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_match_endpoint_id')
                ->nullable()
                ->constrained('customer_match_endpoints', 'id', 'cm_access_endpoint_fk')
                ->nullOnDelete();
            $table->string('slug')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('row_count')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_match_endpoint_id', 'created_at'], 'cm_access_endpoint_created_idx');
            $table->index(['status', 'created_at'], 'cm_access_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_match_access_logs');
    }
};
