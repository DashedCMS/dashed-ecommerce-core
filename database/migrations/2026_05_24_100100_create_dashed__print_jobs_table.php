<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashed__print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('type', 30);
            $table->foreignId('printer_id')->nullable()->constrained('dashed__printers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('dashed__orders')->cascadeOnDelete();
            $table->string('printable_type')->nullable();
            $table->unsignedBigInteger('printable_id')->nullable();
            $table->string('status', 20);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->string('pdf_disk', 32)->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type', 'printer_id']);
            $table->index(['status', 'failed_at']);
            $table->index(['printable_type', 'printable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__print_jobs');
    }
};
