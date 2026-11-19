<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Eigen productreviews, het reviewverzoek na levering en productvragen.
 * Alle drie de tabellen in één migratie: ze horen bij elkaar en een losse
 * add_-migratie zou alfabetisch vóór de create_ kunnen draaien.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__product_reviews')) {
            Schema::create('dashed__product_reviews', function (Blueprint $table) {
                $table->id();
                $table->string('site_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_group_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                // Eén review per gekochte orderregel; null bij een review die
                // een beheerder zelf invoert.
                $table->unsignedBigInteger('order_product_id')->nullable()->unique();
                $table->boolean('verified_buyer')->default(false);
                $table->unsignedTinyInteger('rating');
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('locale', 10)->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->text('reply')->nullable();
                $table->timestamp('replied_at')->nullable();
                $table->unsignedBigInteger('replied_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['product_group_id', 'status']);
                $table->index(['product_id', 'status']);
            });
        }

        if (! Schema::hasTable('dashed__product_review_requests')) {
            Schema::create('dashed__product_review_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->unique();
                $table->string('site_id')->index();
                $table->timestamp('due_at')->index();
                $table->timestamp('sent_at')->nullable();
                $table->string('skipped_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dashed__product_questions')) {
            Schema::create('dashed__product_questions', function (Blueprint $table) {
                $table->id();
                $table->string('site_id')->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->unsignedBigInteger('product_group_id')->nullable()->index();
                $table->string('name');
                $table->string('email');
                $table->text('question');
                $table->text('answer')->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->unsignedBigInteger('answered_by')->nullable();
                $table->timestamp('customer_notified_at')->nullable();
                $table->string('locale', 10)->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->timestamps();

                $table->index(['product_group_id', 'status']);
                $table->index(['product_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__product_questions');
        Schema::dropIfExists('dashed__product_review_requests');
        Schema::dropIfExists('dashed__product_reviews');
    }
};
