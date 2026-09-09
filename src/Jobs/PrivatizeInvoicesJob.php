<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Zet elke bestaande factuur en pakbon op de schijf op prive. Nieuwe bestanden
 * worden al prive weggeschreven (Order::createNormalInvoice en verwanten), maar
 * op een S3-schijf met visibility public was alles van voor die wijziging
 * rechtstreeks op objectnaam te openen, buiten de downloadroute om.
 *
 * Wordt bij de uitrol een keer in de wachtrij gezet door een migratie; de
 * commandoregel (dashed:privatize-invoices) doet hetzelfde met de hand.
 */
class PrivatizeInvoicesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public const DIRECTORIES = ['dashed/invoices', 'dashed/packing-slips'];

    public function handle(): int
    {
        return static::run();
    }

    /**
     * @return int Het aantal bestanden dat op prive is gezet.
     */
    public static function run(?callable $onFile = null): int
    {
        $disk = Storage::disk('dashed');
        $count = 0;

        foreach (self::DIRECTORIES as $directory) {
            foreach ($disk->files($directory) as $file) {
                if (! str_ends_with(strtolower($file), '.pdf')) {
                    continue;
                }

                // Een schijf zonder ondersteuning voor visibility (of een object
                // dat net verwijderd is) mag de ronde niet afbreken.
                rescue(fn () => $disk->setVisibility($file, 'private'), report: false);
                $count++;

                if ($onFile) {
                    $onFile($file);
                }
            }
        }

        return $count;
    }
}
