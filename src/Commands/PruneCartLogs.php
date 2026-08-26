<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;

/**
 * Verouderd: winkelwagenlogboeken staan aangemeld in het bewaartermijnenregister
 * en worden nu opgeruimd door dashed:prune. Dit command blijft bestaan als
 * alias, zodat een bestaande cronregel op een productieserver niet in één
 * klap kapot gaat.
 */
class PruneCartLogs extends Command
{
    protected $signature = 'dashed:prune-cart-logs';

    protected $description = 'Verouderd. Gebruik dashed:prune. Delete cart activity logs older than the retention window.';

    public function handle(): int
    {
        return $this->call('dashed:prune', [
            '--only' => 'cart_logs',
        ]);
    }
}
