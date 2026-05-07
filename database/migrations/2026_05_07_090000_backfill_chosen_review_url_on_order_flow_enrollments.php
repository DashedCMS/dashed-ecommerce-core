<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

return new class () extends Migration {
    /**
     * Vult chosen_review_url op bestaande order-flow-enrollments die nog leeg
     * zijn. Idempotent: rijen met een gevulde URL blijven onaangeroerd.
     * Spiegel van het BackfillOrderFlowEnrollmentReviewUrls-command zodat
     * deploys de data automatisch netzetten zonder dat de admin de command
     * handmatig hoeft te draaien.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dashed__order_flow_enrollments')) {
            return;
        }
        if (! Schema::hasColumn('dashed__order_flow_enrollments', 'chosen_review_url')) {
            return;
        }

        OrderFlowEnrollment::query()
            ->where(function ($q) {
                $q->whereNull('chosen_review_url')->orWhere('chosen_review_url', '');
            })
            ->with('flow')
            ->chunkById(500, function ($enrollments) {
                foreach ($enrollments as $enrollment) {
                    $picked = $enrollment->flow?->pickReviewUrl();
                    if (! $picked || empty($picked['url'])) {
                        continue;
                    }

                    $enrollment->forceFill([
                        'chosen_review_url' => $picked['url'],
                        'chosen_review_url_label' => $picked['label'] ?? null,
                    ])->save();
                }
            });
    }

    public function down(): void
    {
        // No-op: we kunnen niet onderscheiden welke chosen_review_url door
        // de backfill is gezet en welke door de listener / mail-render. Liever
        // niets terugdraaien dan correcte data wissen.
    }
};
