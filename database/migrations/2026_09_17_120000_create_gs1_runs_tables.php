<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__gs1_runs')) {
            Schema::create('dashed__gs1_runs', function (Blueprint $table) {
                $table->id();
                $table->string('site_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('file_path');
                $table->string('contract_sheet')->nullable();
                $table->string('status')->default('concept')->index();
                $table->string('result_path')->nullable();
                $table->json('summary')->nullable();
                $table->longText('reference_data')->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('dashed__gs1_run_lines')) {
            Schema::create('dashed__gs1_run_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gs1_run_id')->constrained('dashed__gs1_runs')->cascadeOnDelete();
                $table->string('gtin')->index();
                $table->json('sheet_rows');
                $table->string('gs1_status')->nullable();
                $table->string('gs1_description', 500)->nullable();
                $table->string('kind')->index();
                $table->string('decision')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('previous_product_id')->nullable();
                $table->boolean('reused_inactive')->default(false);
                $table->string('skip_reason')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamp('reverted_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__gs1_run_lines');
        Schema::dropIfExists('dashed__gs1_runs');
    }
};
