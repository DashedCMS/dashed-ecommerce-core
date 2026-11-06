<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands\PrintQueue;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

class RetryFailedPrintJobsCommand extends Command
{
    protected $signature = 'print-queue:retry-failed';

    protected $description = 'Promote failed PrintJobs back to pending wanneer cooldown verlopen en attempts < max_retries';

    /**
     * Snelle controle voor de scheduler: zonder werk start er geen eigen
     * artisan-proces. Lukt de query niet, dan draait het commando gewoon. De
     * limiet staat hier ook in, anders houdt een opgegeven job de poort open.
     */
    public static function hasWork(): bool
    {
        return rescue(
            fn () => PrintJob::query()
                ->leftJoin('dashed__printers', 'dashed__printers.id', '=', 'dashed__print_jobs.printer_id')
                ->where('dashed__print_jobs.status', PrintJobStatus::Failed->value)
                ->where('dashed__print_jobs.failed_at', '<', now()->subMinutes(2))
                ->whereRaw('dashed__print_jobs.attempts < COALESCE(dashed__printers.max_retries, 3)')
                ->exists(),
            true,
            false,
        );
    }

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
