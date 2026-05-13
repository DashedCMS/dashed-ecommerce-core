<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Jobs\PrecomputeCoPurchaseScoresJob;

/**
 * Manual trigger for the recommendation co-purchase precompute job.
 *
 * Usage:
 *   php artisan dashed:recommendations:rebuild              # incremental
 *   php artisan dashed:recommendations:rebuild --full       # full rebuild
 *
 * Runs the job synchronously so cron scripts can rely on exit status.
 */
class RebuildRecommendationCoPurchaseCommand extends Command
{
    protected $signature = 'dashed:recommendations:rebuild {--full : Rebuild every pair from scratch and prune stale rows}';

    protected $description = 'Recompute product co-purchase scores for the FrequentlyBoughtTogether strategy.';

    public function handle(): int
    {
        $mode = $this->option('full') ? 'full' : 'incremental';
        $this->info("Running PrecomputeCoPurchaseScoresJob in {$mode} mode…");

        PrecomputeCoPurchaseScoresJob::dispatchSync($mode);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
