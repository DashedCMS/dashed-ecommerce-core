<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Services\BackInStockService;

class NotifyBackInStockCommand extends Command
{
    protected $signature = 'dashed:notify-back-in-stock';

    protected $description = 'Mailt klanten die wachten op een terug-op-voorraad-melding, voor producten die weer koopbaar zijn.';

    public function handle(BackInStockService $service): int
    {
        $sent = $service->notifyPending();
        $this->info("Terug-op-voorraad: {$sent} melding(en) verstuurd.");

        return self::SUCCESS;
    }
}
