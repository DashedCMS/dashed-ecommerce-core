<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Jobs\PrivatizeProductExtraFiles;

class PrivatizeProductExtraFilesCommand extends Command
{
    protected $signature = 'dashed-ecommerce:privatize-product-extra-files {--now : Direct uitvoeren in plaats van via de queue}';

    protected $description = "Verhuist bestanden bij product-extra's in orders naar de privé opslag";

    public function handle(): int
    {
        $this->option('now') ? PrivatizeProductExtraFiles::dispatchSync() : PrivatizeProductExtraFiles::dispatch();
        $this->info($this->option('now') ? 'Klaar; zie de log voor de aantallen.' : 'In de queue gezet.');

        return self::SUCCESS;
    }
}
