<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

class BackfillOrderFlowEnrollmentNextMailAt extends Command
{
    protected $signature = 'dashed:backfill-order-flow-enrollment-next-mail-at {--all : Ook bijwerken als next_mail_at al gevuld is}';

    protected $description = 'Vul next_mail_at op bestaande order-flow-enrollments op basis van de huidige flow-config en reeds verzonden stappen.';

    public function handle(): int
    {
        $updated = 0;
        $cleared = 0;
        $skipped = 0;
        $only = ! $this->option('all');

        $query = OrderFlowEnrollment::query()->with('flow.steps');
        if ($only) {
            // Standaard alleen rijen waar het veld nog leeg is. Met --all
            // forceer je een herrekening over alles (handig na flow-edits).
            $query->whereNull('next_mail_at');
        }

        $query->chunkById(500, function ($enrollments) use (&$updated, &$cleared, &$skipped) {
            foreach ($enrollments as $enrollment) {
                if (! $enrollment->flow) {
                    $skipped++;

                    continue;
                }

                $previous = $enrollment->next_mail_at?->toIso8601String();
                $enrollment->recomputeNextMailAt();
                $enrollment->refresh();
                $current = $enrollment->next_mail_at?->toIso8601String();

                if ($current === $previous) {
                    $skipped++;
                } elseif ($current === null) {
                    $cleared++;
                } else {
                    $updated++;
                }
            }
        });

        $this->info("Gezet: {$updated} | Geleegd: {$cleared} | Ongewijzigd: {$skipped}.");

        return self::SUCCESS;
    }
}
