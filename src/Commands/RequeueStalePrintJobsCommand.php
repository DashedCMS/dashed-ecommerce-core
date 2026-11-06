<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

class RequeueStalePrintJobsCommand extends Command
{
    protected $signature = 'dashed-ecommerce:requeue-stale-print-jobs {--minutes=10 : Na hoeveel minuten een geclaimde job als vastgelopen geldt}';

    protected $description = 'Zet print-jobs die te lang op "claimed" staan terug naar "pending" zodat ze opnieuw worden opgepakt.';

    /**
     * Snelle controle voor de scheduler: zonder werk start er geen eigen
     * artisan-proces. Lukt de controle niet, dan draait het commando gewoon.
     */
    public static function hasWork(): bool
    {
        return rescue(fn () => self::staleJobs(now()->subMinutes(10))->exists(), true, false);
    }

    private static function staleJobs(\DateTimeInterface $threshold)
    {
        return PrintJob::query()
            ->where('status', PrintJobStatus::Claimed->value)
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<', $threshold);
    }

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $threshold = now()->subMinutes($minutes > 0 ? $minutes : 10);

        $stale = self::staleJobs($threshold)->get();

        foreach ($stale as $job) {
            $job->retry();
        }

        $this->info('Teruggezet naar pending: ' . $stale->count() . '.');

        return self::SUCCESS;
    }
}
