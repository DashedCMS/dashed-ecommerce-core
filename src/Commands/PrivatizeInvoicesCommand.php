<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Jobs\PrivatizeInvoicesJob;

class PrivatizeInvoicesCommand extends Command
{
    protected $signature = 'dashed:privatize-invoices';

    protected $description = 'Zet alle bestaande facturen en pakbonnen op de dashed-schijf op prive, zodat ze alleen nog via de ondertekende downloadroute te openen zijn';

    public function handle(): int
    {
        $count = PrivatizeInvoicesJob::run(fn (string $file) => $this->line($file));

        $this->info($count . ' bestand(en) op prive gezet.');

        return self::SUCCESS;
    }
}
