<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Retention;

use Carbon\Carbon;
use Dashed\DashedCore\Retention\Termijn;
use Dashed\DashedEcommerceCore\Models\Gs1Run;
use Dashed\DashedCore\Retention\Contracts\Opruimer;

/**
 * Via het model, zoals bij exports: Gs1Run::deleting haalt het origineel en
 * het uploadbestand van de schijf. Een kale databaseverwijdering laat die
 * bestanden voorgoed staan.
 */
class Gs1RunsOpruimer implements Opruimer
{
    public function ruimOp(Termijn $termijn, int $portie, bool $droog): int
    {
        $grens = Carbon::now()->subDays($termijn->dagen());

        if ($droog) {
            return Gs1Run::where($termijn->datumkolom(), '<', $grens)->count();
        }

        $aantal = 0;

        Gs1Run::where($termijn->datumkolom(), '<', $grens)
            ->chunkById($portie, function ($runs) use (&$aantal) {
                foreach ($runs as $run) {
                    $run->delete();
                    $aantal++;
                }
            });

        return $aantal;
    }
}
