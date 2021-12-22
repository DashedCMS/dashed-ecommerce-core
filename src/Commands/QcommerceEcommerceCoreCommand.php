<?php

namespace Qubiqx\QcommerceEcommerceCore\Commands;

use Illuminate\Console\Command;

class QcommerceEcommerceCoreCommand extends Command
{
    public $signature = 'qcommerce-ecommerce-core';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
