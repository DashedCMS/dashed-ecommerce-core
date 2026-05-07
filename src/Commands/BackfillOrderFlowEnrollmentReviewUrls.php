<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

class BackfillOrderFlowEnrollmentReviewUrls extends Command
{
    protected $signature = 'dashed:backfill-order-flow-enrollment-review-urls';

    protected $description = 'Vul chosen_review_url op order-flow-enrollments die nog leeg zijn, op basis van de huidige flow-config (gewogen draw + Customsetting fallback)';

    public function handle(): int
    {
        $filled = 0;
        $skipped = 0;

        OrderFlowEnrollment::query()
            ->where(function ($q) {
                $q->whereNull('chosen_review_url')->orWhere('chosen_review_url', '');
            })
            ->with('flow')
            ->chunkById(500, function ($enrollments) use (&$filled, &$skipped) {
                foreach ($enrollments as $enrollment) {
                    $picked = $enrollment->flow?->pickReviewUrl();
                    if (! $picked || empty($picked['url'])) {
                        $skipped++;

                        continue;
                    }

                    $enrollment->forceFill([
                        'chosen_review_url' => $picked['url'],
                        'chosen_review_url_label' => $picked['label'] ?? null,
                    ])->save();
                    $filled++;
                }
            });

        $this->info("Gevuld: {$filled} enrollments. Overgeslagen (geen flow / geen URLs ingesteld): {$skipped}.");

        return self::SUCCESS;
    }
}
