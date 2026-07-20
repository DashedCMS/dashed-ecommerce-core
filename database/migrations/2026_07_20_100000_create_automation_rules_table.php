<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__automation_rules')) {
            return;
        }

        Schema::create('dashed__automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('site_id');
            $table->string('name');
            $table->string('trigger');
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Voor het matchen: "welke actieve regels luisteren naar deze trigger op deze site".
            $table->index(['site_id', 'trigger', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__automation_rules');
    }
};
