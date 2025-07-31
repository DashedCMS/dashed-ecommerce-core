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
        if (Schema::hasTable('dashed__product_faqs')) {
            return;
        }

        Schema::create('dashed__product_faqs', function (Blueprint $table) {
            $table->id();

            $table->json('name');
            $table->json('questions');
            $table->integer('order')
                ->default(0);
            $table->boolean('global')
                ->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dashed__product_faq_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('dashed__products')
                ->cascadeOnDelete();
            $table->foreignId('faq_id')
                ->constrained('dashed__product_faqs')
                ->cascadeOnDelete();
        });

        Schema::create('dashed__product_faq_product_category', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_faq_id')
                ->constrained('dashed__product_faqs')
                ->cascadeOnDelete();
            $table->foreignId('product_category_id')
                ->constrained(
                    'dashed__product_categories',
                    'id',
                    'product_category_faqs_fk'
                )
                ->cascadeOnDelete();

            $table->timestamps();
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
