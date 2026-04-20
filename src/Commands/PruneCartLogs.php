<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\CartLog;

class PruneCartLogs extends Command
{
    protected $signature = 'dashed:prune-cart-logs {--days=90 : Retention in days}';

    protected $description = 'Delete cart activity logs older than the retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = CartLog::where('created_at', '<', $cutoff)->delete();

        $this->info(sprintf('Pruned %d cart log(s) older than %d days.', $deleted, $days));

        return self::SUCCESS;
    }
}
