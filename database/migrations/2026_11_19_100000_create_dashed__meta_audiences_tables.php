<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__meta_audiences')) {
            Schema::create('dashed__meta_audiences', function (Blueprint $table) {
                $table->id();
                $table->string('site_id');
                $table->string('name');
                $table->string('source_type');
                $table->unsignedBigInteger('source_id');
                $table->boolean('is_active')->default(true);
                $table->string('meta_audience_id')->nullable();
                $table->unsignedInteger('member_count')->default(0);
                $table->string('sync_status')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamp('last_full_sync_at')->nullable();
                $table->timestamps();

                $table->index(['site_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('dashed__meta_audience_members')) {
            Schema::create('dashed__meta_audience_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meta_audience_id')->constrained('dashed__meta_audiences')->cascadeOnDelete();
                $table->char('email_hash', 64);
                $table->char('phone_hash', 64)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->unique(['meta_audience_id', 'email_hash'], 'meta_audience_members_unique');
            });
        }

        if (! Schema::hasTable('dashed__meta_audience_logs')) {
            Schema::create('dashed__meta_audience_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meta_audience_id')->constrained('dashed__meta_audiences')->cascadeOnDelete();
                $table->string('type');
                $table->string('status');
                $table->unsignedInteger('total')->nullable();
                $table->unsignedInteger('added')->default(0);
                $table->unsignedInteger('removed')->default(0);
                $table->text('message')->nullable();
                $table->timestamps();

                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__meta_audience_logs');
        Schema::dropIfExists('dashed__meta_audience_members');
        Schema::dropIfExists('dashed__meta_audiences');
    }
};
