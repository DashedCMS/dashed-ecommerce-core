<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Voegt een `sent_steps`-JSON-kolom toe aan dashed__order_flow_enrollments
 * waarin per stap-id de verzend-timestamp wordt bijgehouden:
 *
 *   { "12": "2026-05-07T09:00:00+00:00", "13": "2026-05-14T09:00:00+00:00" }
 *
 * SendOrderHandledEmailJob schrijft hier een entry naartoe na een succesvolle
 * Mail::send. De Filament-tabel + stats-widget lezen hieruit hoeveel mails per
 * enrollment al verstuurd zijn en hoeveel er in totaal vanuit deze flow naar
 * buiten zijn gegaan.
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments')) {
            return;
        }

        if (Schema::hasColumn('dashed__order_flow_enrollments', 'sent_steps')) {
            return;
        }

        Schema::table('dashed__order_flow_enrollments', function (Blueprint $table) {
            $table->json('sent_steps')->nullable()->after('chosen_review_url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments')) {
            return;
        }

        if (! Schema::hasColumn('dashed__order_flow_enrollments', 'sent_steps')) {
            return;
        }

        Schema::table('dashed__order_flow_enrollments', function (Blueprint $table) {
            $table->dropColumn('sent_steps');
        });
    }
};
