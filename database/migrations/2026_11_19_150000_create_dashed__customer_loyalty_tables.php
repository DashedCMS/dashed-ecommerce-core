<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Verwijsprogramma (codes en verwijzingen) en notities bij een klant.
 * Een klant is een account of, voor een gast, alleen een e-mailadres.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__referral_codes')) {
            Schema::create('dashed__referral_codes', function (Blueprint $table) {
                $table->id();
                $table->string('site_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('email')->index();
                $table->string('code')->unique();
                $table->unsignedBigInteger('discount_code_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dashed__referrals')) {
            Schema::create('dashed__referrals', function (Blueprint $table) {
                $table->id();
                $table->string('site_id')->index();
                $table->unsignedBigInteger('referral_code_id')->index();
                $table->unsignedBigInteger('order_id')->unique();
                $table->string('referred_email')->index();
                $table->string('status')->default('pending')->index();
                $table->string('reject_reason')->nullable();
                $table->timestamp('qualifies_at')->nullable()->index();
                $table->unsignedBigInteger('reward_discount_code_id')->nullable();
                $table->timestamp('rewarded_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dashed__customer_notes')) {
            Schema::create('dashed__customer_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('email')->nullable()->index();
                $table->unsignedBigInteger('author_id')->nullable();
                $table->text('note');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__customer_notes');
        Schema::dropIfExists('dashed__referrals');
        Schema::dropIfExists('dashed__referral_codes');
    }
};
