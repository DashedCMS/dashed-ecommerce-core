<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands\PrintQueue;

use Illuminate\Console\Command;

/**
 * Verouderd: afdrukopdrachten staan aangemeld in het bewaartermijnenregister
 * en worden nu opgeruimd door dashed:prune. Dit command blijft bestaan als
 * alias, zodat een bestaande cronregel op een productieserver niet in één
 * klap kapot gaat.
 */
class CleanupOldPrintJobsCommand extends Command
{
    protected $signature = 'print-queue:cleanup-old-jobs';

    protected $description = 'Verouderd. Gebruik dashed:prune. Verwijder oude done/cancelled en zeer oude failed PrintJobs';

    public function handle(): int
    {
        return $this->call('dashed:prune', [
            '--only' => 'print_jobs',
        ]);
    }
}
