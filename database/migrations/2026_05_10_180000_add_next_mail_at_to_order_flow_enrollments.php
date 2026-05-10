<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments')) {
            return;
        }

        if (! Schema::hasColumn('dashed__order_flow_enrollments', 'next_mail_at')) {
            Schema::table('dashed__order_flow_enrollments', function (Blueprint $table) {
                $table->timestamp('next_mail_at')->nullable()->after('sent_steps');
                $table->index('next_mail_at');
            });
        }

        // Eénmalige backfill: bestaande enrollments die nog geen
        // `next_mail_at` hebben, herrekenen op basis van de huidige flow-
        // config en reeds verzonden stappen. Migrations draaien per
        // omgeving 1x dus dit fired alleen op installaties die nog niet
        // gemigreerd waren. De command `dashed:backfill-order-flow-
        // enrollment-next-mail-at` blijft beschikbaar voor handmatige
        // hercomputaties (bv. na flow-edits).
        OrderFlowEnrollment::query()
            ->whereNull('next_mail_at')
            ->with('flow.steps')
            ->chunkById(500, function ($enrollments) {
                foreach ($enrollments as $enrollment) {
                    if (! $enrollment->flow) {
                        continue;
                    }
                    try {
                        $enrollment->recomputeNextMailAt();
                    } catch (\Throwable $e) {
                        // Niet fataal — een corrupte enrollment mag de
                        // migratie niet blokkeren. De command kan later
                        // alsnog gerund worden.
                        report($e);
                    }
                }
            });
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
