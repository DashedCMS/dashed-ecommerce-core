<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands\PrintQueue;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Illuminate\Console\Command;

class CleanupOldPrintJobsCommand extends Command
{
    protected $signature = 'print-queue:cleanup-old-jobs';

    protected $description = 'Verwijder oude done/cancelled en zeer oude failed PrintJobs';

    public function handle(): int
    {
        $doneRetention = (int) Customsetting::get('print_queue.job_retention_days', null, 90);
        $failedRetention = (int) Customsetting::get('print_queue.failed_job_retention_days', null, 365);

        $deletedDone = PrintJob::query()
            ->whereIn('status', [PrintJobStatus::Done->value, PrintJobStatus::Cancelled->value])
            ->where('updated_at', '<', now()->subDays($doneRetention))
            ->delete();

        $deletedFailed = PrintJob::query()
            ->where('status', PrintJobStatus::Failed->value)
            ->where('failed_at', '<', now()->subDays($failedRetention))
            ->delete();

        $this->info("Deleted {$deletedDone} done/cancelled jobs and {$deletedFailed} ancient failed jobs.");

        return self::SUCCESS;
    }
}
