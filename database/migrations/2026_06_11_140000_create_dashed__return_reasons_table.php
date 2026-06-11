<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__return_reasons')) {
            return;
        }

        Schema::create('dashed__return_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->nullable()->index();
            $table->json('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__return_reasons');
    }
};
