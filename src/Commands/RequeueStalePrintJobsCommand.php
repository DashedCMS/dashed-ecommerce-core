<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

class RequeueStalePrintJobsCommand extends Command
{
    protected $signature = 'dashed-ecommerce:requeue-stale-print-jobs {--minutes=10 : Na hoeveel minuten een geclaimde job als vastgelopen geldt}';

    protected $description = 'Zet print-jobs die te lang op "claimed" staan terug naar "pending" zodat ze opnieuw worden opgepakt.';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $threshold = now()->subMinutes($minutes > 0 ? $minutes : 10);

        $stale = PrintJob::query()
            ->where('status', PrintJobStatus::Claimed->value)
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<', $threshold)
            ->get();

        foreach ($stale as $job) {
            $job->retry();
        }

        $this->info('Teruggezet naar pending: ' . $stale->count() . '.');

        return self::SUCCESS;
    }
}
