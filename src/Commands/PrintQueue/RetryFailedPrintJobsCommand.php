<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands\PrintQueue;

use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Illuminate\Console\Command;

class RetryFailedPrintJobsCommand extends Command
{
    protected $signature = 'print-queue:retry-failed';

    protected $description = 'Promote failed PrintJobs back to pending wanneer cooldown verlopen en attempts < max_retries';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(2);

        $promoted = 0;

        PrintJob::query()
            ->where('status', PrintJobStatus::Failed->value)
            ->where('failed_at', '<', $cutoff)
            ->with('printer')
            ->chunkById(100, function ($jobs) use (&$promoted): void {
                foreach ($jobs as $job) {
                    $cap = $job->printer?->max_retries ?? 3;
                    if ($job->attempts >= $cap) {
                        continue;
                    }

                    $job->retry();
                    $promoted++;
                }
            });

        $this->info("Promoted {$promoted} jobs back to pending.");

        return self::SUCCESS;
    }
}
