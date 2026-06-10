<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__product_finders')) {
            return;
        }
        Schema::create('dashed__product_finders', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->nullable()->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('intro')->nullable();
            $table->json('questions')->nullable();
            $table->unsignedInteger('result_count')->default(4);
            $table->json('category_ids')->nullable();
            $table->boolean('only_in_stock')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__product_finders');
    }
};
