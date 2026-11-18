<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Throwable;
use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Services\Address\BelgianAddresses;

/**
 * Vult of ververst de Belgische straten en gemeentes (BOSA open data). De planner
 * draait dit vanzelf zodra een site `checkout_belgian_addresses` aan heeft.
 */
class ImportBelgianAddressesCommand extends Command
{
    protected $signature = 'dashed:import-belgian-addresses {--file=* : Lokale zip/csv in plaats van de downloads}';

    protected $description = 'Importeer Belgische straten en gemeentes voor het adres aanvullen (BOSA open data)';

    public function handle(BelgianAddresses $addresses): int
    {
        @ini_set('memory_limit', '1G');

        try {
            $counts = $addresses->import($this->option('file') ?: null, fn (string $line) => $this->line($line));
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Klaar: {$counts['streets']} straten in {$counts['postcodes']} postcodes.");

        return self::SUCCESS;
    }
}
