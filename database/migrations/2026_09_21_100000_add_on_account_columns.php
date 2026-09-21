<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('dashed__payment_methods', 'on_account')) {
            Schema::table('dashed__payment_methods', function (Blueprint $table) {
                $table->boolean('on_account')->default(false)->after('psp');
            });
        }

        if (! Schema::hasColumn('users', 'payment_term_days')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedSmallInteger('payment_term_days')->nullable();
                $table->decimal('credit_limit', 12, 2)->nullable();
                $table->timestamp('on_account_blocked_at')->nullable();
            });
        }

        if (! Schema::hasColumn('dashed__orders', 'payment_due_at')) {
            Schema::table('dashed__orders', function (Blueprint $table) {
                $table->timestamp('payment_due_at')->nullable()->index();
                $table->timestamp('payment_reminders_paused_at')->nullable();
            });
        }

        if (! Schema::hasColumn('dashed__order_payments', 'credit_order_id')) {
            Schema::table('dashed__order_payments', function (Blueprint $table) {
                $table->foreignId('credit_order_id')->nullable()->constrained('dashed__orders')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('dashed__order_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_order_id');
        });
        Schema::table('dashed__orders', function (Blueprint $table) {
            $table->dropIndex(['payment_due_at']);
            $table->dropColumn(['payment_due_at', 'payment_reminders_paused_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['payment_term_days', 'credit_limit', 'on_account_blocked_at']);
        });
        Schema::table('dashed__payment_methods', function (Blueprint $table) {
            $table->dropColumn('on_account');
        });
    }
};
