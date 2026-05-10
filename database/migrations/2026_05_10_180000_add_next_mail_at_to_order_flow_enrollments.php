<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashed__order_flow_enrollments')
            && ! Schema::hasColumn('dashed__order_flow_enrollments', 'next_mail_at')) {
            Schema::table('dashed__order_flow_enrollments', function (Blueprint $table) {
                $table->timestamp('next_mail_at')->nullable()->after('sent_steps');
                $table->index('next_mail_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dashed__order_flow_enrollments')
            && Schema::hasColumn('dashed__order_flow_enrollments', 'next_mail_at')) {
            Schema::table('dashed__order_flow_enrollments', function (Blueprint $table) {
                $table->dropIndex(['next_mail_at']);
                $table->dropColumn('next_mail_at');
            });
        }
    }
};
