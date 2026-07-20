<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__automation_rule_runs')) {
            return;
        }

        Schema::create('dashed__automation_rule_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('dashed__automation_rules')->cascadeOnDelete();
            $table->string('site_id');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('trigger');
            $table->string('status', 20);
            $table->json('results')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            // Voor het log en de loop-guard: "draaide deze regel al recent voor dit subject".
            $table->index(['rule_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__automation_rule_runs');
    }
};
