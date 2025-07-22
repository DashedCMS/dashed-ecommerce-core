<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dashed__product_faq_product_group', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_group_id')
                ->constrained('dashed__product_groups')
                ->cascadeOnDelete();
            $table->foreignId('faq_id')
                ->constrained('dashed__product_faqs')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
